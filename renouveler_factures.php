#!/usr/bin/php
<?php

if (isset($argv[1])) {
	$t = strtotime($argv[1]);
	if (!$t) {
		die('Format date invalide '.$argv[1]);
	}
	// il faut poser le first_day_of_month sur le 1er jour du mois suivant que l'on veut
	$t = strtotime(date('Y-m-28 00:00:00', $t)) + 7 * 24 * 3600;
	$first_day_of_month = date('Y-m-01 00:00:00', $t);
}
else {
	$first_day_of_month = date('Y-m-01 00:00:00');
}

$last_day_of_previous_month = date('Y-m-d 23:59:59', strtotime($first_day_of_month)-7200);
$first_day_of_previous_month = date('Y-m-01 00:00:00', strtotime($last_day_of_previous_month));


// par defaut le mois precedent
$time_start = strtotime($first_day_of_previous_month);
$time_end = strtotime($last_day_of_previous_month);

/**
 *    \file       htdocs/compta/journal/sellsjournal.php
 *    \ingroup    societe, facture
 *    \brief      Page with sells journal
 */
global $mysoc;

require __DIR__ . '/doli_boot.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/report.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/client.class.php';

// Load translation files required by the page
//$langs->loadLangs(array('companies', 'other', 'compta'));


// Security check
if ($user->societe_id>0){
	$socid = $user->societe_id;
}

/*
 * View
 */

$date_start = $time_start;
$date_end = $time_end;

$p = explode(":", $conf->global->MAIN_INFO_SOCIETE_COUNTRY);
$idpays = $p[0];

$sql = "SELECT f.rowid, f.facnumber, f.type, f.datef, f.ref_client,";
$sql .= " fd.product_type, fd.description, fd.qty, fd.subprice, fd.total_ht, fd.total_tva, fd.tva_tx, fd.total_ttc, fd.localtax1_tx, fd.localtax2_tx, fd.total_localtax1, fd.total_localtax2, fd.rowid as id, fd.situation_percent,";
$sql .= " s.rowid as socid, s.nom as name, s.code_compta, s.client,";
$sql .= " p.rowid as pid, p.ref as pref,p.label as plabel, p.accountancy_code_sell,";
$sql .= " ct.accountancy_code_sell as account_tva, ct.recuperableonly";
$sql .= " FROM " . MAIN_DB_PREFIX . "facturedet as fd";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "product as p ON p.rowid = fd.fk_product";
$sql .= " JOIN " . MAIN_DB_PREFIX . "facture as f ON f.rowid = fd.fk_facture";
$sql .= " JOIN " . MAIN_DB_PREFIX . "societe as s ON s.rowid = f.fk_soc";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "c_tva ct ON fd.tva_tx = ct.taux AND fd.info_bits = ct.recuperableonly AND ct.fk_pays = '" . $idpays . "'";
$sql .= " WHERE f.entity = " . $conf->entity;
$sql .= " AND f.fk_statut > 0";
if (!empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS)){
	$sql .= " AND f.type IN (" . Facture::TYPE_STANDARD . "," . Facture::TYPE_REPLACEMENT . "," . Facture::TYPE_CREDIT_NOTE . "," . Facture::TYPE_SITUATION . ")";
} else {
	$sql .= " AND f.type IN (" . Facture::TYPE_STANDARD . "," . Facture::TYPE_STANDARD . "," . Facture::TYPE_CREDIT_NOTE . "," . Facture::TYPE_DEPOSIT . "," . Facture::TYPE_SITUATION . ")";
}

$sql .= " AND fd.product_type IN (0,1)";
if ($date_start && $date_end){
	$sql .= " AND f.datef >= '" . $db->idate($date_start) . "' AND f.datef <= '" . $db->idate($date_end) . "'";
}
$sql .= " ORDER BY f.rowid";

// TODO Find a better trick to avoid problem with some mysql installations
if (in_array($db->type, array('mysql', 'mysqli'))){
	$db->query('SET SQL_BIG_SELECTS=1');
}

$refacturer = [];

$result = $db->query($sql);
if ($result){
	$num = $db->num_rows($result);
	$i = 0;
	while ($i<$num){
		$obj = $db->fetch_object($result);

		// que les formules mensueles temps partiel ou temps plein et supplement bureau fixe
		if (stripos($obj->plabel, 'mensuel') !== false
			or stripos($obj->description, 'mensuel') !== false
		  or stripos($obj->plabel, 'temps plein') !== false
		  or (stripos($obj->plabel, 'suppl') !== false && stripos($obj->plabel, 'bureau') !== false)) {

			// pas les remboursements !
			if ($obj->total_ht > 0) {
				$ref_facture = $obj->facnumber;
				if (!isset($refacturer[$ref_facture])) {
					$refacturer[$ref_facture] = [
						'socid' => $obj->socid,
						'name' => $obj->name,
						'lines' => [],
					];
				}
				$line = [
					'id_produit' => $obj->pid,
					'quantite' => $obj->qty,
					'prix_unitaire' => $obj->subprice,
					'taux_tva' => $obj->tva_tx,
					'total_ht' => $obj->total_ht,
					'total_tva' => $obj->total_tva,
					'total_ttc' => $obj->total_ttc,
					'libelle' => $obj->description,
				];
				$refacturer[$ref_facture]['lines'][] = $line;
			}
		}
		$i++;
	}
}


// et on refacture !
$i = 0;
$nb_success = 0;
$nb_echec = 0;
$nb_ignore = 0;
foreach ($refacturer as $ref=>$facture) {
	$i++;
	if (($deja_ref = cherche_deja_brouillon($facture['socid'])) !== false) {
		echo "\t";
	}
	echo "$i. SOCID:".$facture['socid']." - ".$facture['name']."    (d'apres $ref)\n";
	foreach ($facture['lines'] as $line) {
		echo "\t" . implode('|', $line) . "\n";
	}


	if ($deja_ref !== false) {
		echo "\tDEJA preparee en brouillon ce mois\n";
		$nb_ignore++;
	}
	else {

		if ($res = doli_facture_inserer($facture['socid'], $facture['lines'])) {
			echo "OK facture a valider " . $res['reference'] . "\n";
			$nb_success++;
		}
		else {
			echo "ECHEC creation facture" . $res['reference'] . "\n";
			$nb_echec++;
		}
	}
	echo "\n";
}

echo "$nb_success factures générées";
if ($nb_echec) {
	echo " / $nb_echec FAIL";
}
if ($nb_ignore) {
	echo " / $nb_ignore IGNORE";
}
echo "\n";

function cherche_deja_brouillon($socid) {
	global $db, $user;
	static $last_rowid = null;
	$month = date('Ym');

	// la derniere facture avant cette session d'insertion car une meme socid peut avoir plusieurs factures dans cette serie
	if (is_null($last_rowid)) {
		$result = $db->query("SELECT rowid FROM " . MAIN_DB_PREFIX . "facture ORDER BY rowid DESC LIMIT 0,1");
		$obj = $db->fetch_object($result);
		$last_rowid = $obj->rowid;
	}

	$sql = "SELECT f.rowid, f.facnumber"
	 . " FROM " . MAIN_DB_PREFIX . "facture as f"
	 . " WHERE f.fk_soc = '" . addslashes($socid)."' AND f.fk_statut = 0 AND date_format(f.datec,'%Y%m') = '$month' AND f.rowid<=" . intval($last_rowid);

	$result = $db->query($sql);
	if ($result and $obj = $db->fetch_object($result)) {
		return $obj->facnumber;
	}

	return false;
}


/**
 * @param $socid int
 *      le Numéro de client pour la facture
 * @param $lignes array
 *      le tableau contenant les lignes
 *                          'id_produit' => 2
 *                          'quantite' =>2,
 *                          'prix_unitaire'=>100,
 *                          'taux_tva' => 20,
 *                          'total_ht' => 200,
 *                          'total_tva'=>40,
 *                          'total_ttc' => 240,
 *                          'libelle' => "Hello world"
 *
 * @return array|bool
 */

function doli_facture_inserer($socid, $lignes, $validate = false) {
	global $db, $user;

	require_once(DOL_DOCUMENT_ROOT . "/compta/facture/class/facture.class.php");

	// Start of transaction
	$db->begin();

	$error = 0;

	// Create invoice object
	$facture = new Facture($db);
	$societe = new Societe($db);
	$societe->fetch($socid);
	$facture->socid = $socid; // Put id of third party (rowid in llx_societe table)

	$facture->date = mktime();
	$facture->cond_reglement_id = 1;

	foreach ($lignes as $ligne) {
		//$product = new Product ($db);
		//$product->fetch($ligne['id_produit']);

		$line1 = new FactureLigne($db);
		$line1->qty = $ligne['quantite'];
		$line1->subprice = $ligne['prix_unitaire'];
		$line1->tva_tx = $ligne['taux_tva'];

		$line1->total_ht = $ligne['total_ht'];
		$line1->total_tva = $ligne['total_tva'];
		$line1->total_ttc = $ligne['total_ttc'];
		$line1->desc = $ligne['libelle'];
		$line1->fk_product = $ligne['id_produit'];

		$facture->lines[] = $line1;
	}

	// Create invoice
	$idobject = $facture->create($user);
	if ($idobject > 0) {
		if ($validate) {
			// Change status to validated
			$result = $facture->validate($user);
			if ($result > 0) {
				$db->commit();
				return array(
					'reference' => $facture->ref,
					'id' => $idobject,
				);
			}
		}
		else {
			$db->commit();
			return array(
				'reference' => $facture->ref,
				'id' => $idobject,
			);
		}
	}

	$db->rollback();
	return false;
}