#!/usr/bin/php
<?php

$first_day_of_month = date('Y-m-01 00:00:00');
$comptes = [];

$args = $argv;
array_shift($args);
while (count($args)) {
	$arg = array_shift($args);
	if (intval($arg)) {
		$t = strtotime($arg);
		if (!$t) {
			die('Format date invalide '.$arg[1]);
		}
		// il faut poser le first_day_of_month sur le 1er jour du mois suivant que l'on veut
		$t = strtotime(date('Y-m-28 00:00:00', $t)) + 7 * 24 * 3600;
		$first_day_of_month = date('Y-m-01 00:00:00', $t);
	}
	else {
		// c'est une ref de compte banquaire
		$comptes[] = $arg;
	}
}

$month_export = date('Ym', strtotime($first_day_of_month)-7200);

/**
 *    \file       htdocs/compta/journal/sellsjournal.php
 *    \ingroup    societe, facture
 *    \brief      Page with sells journal
 */
global $mysoc;

require __DIR__ . '/../doli_boot.php';

/*
SELECT DISTINCT b.rowid as b_rowid, ba.ref as ba_ref, ba.label as ba_label, b.dateo as b_dateo, b.label as b_label, b.num_chq as b_num_chq, b.amount as b_amount, b.emetteur as b_emetteur, s.code_compta as s_code_compta, s.nom as s_nom, bu.url_id as url_id_company, concat(a.firstname, ' ', a.lastname) as a_nom, sa.code_compta as sa_code_compta, sa.nom as sa_nom
FROM (lx_bank_account as ba, lx_bank as b)
LEFT JOIN lx_bank_url as bu ON (bu.fk_bank = b.rowid AND bu.type = 'company') LEFT JOIN lx_societe as s ON bu.url_id = s.rowid
LEFT JOIN lx_bank_url as bua ON (bua.fk_bank = b.rowid AND bua.type = 'member') LEFT JOIN lx_adherent as a ON bua.url_id = a.rowid LEFT JOIN lx_societe as sa ON a.fk_soc = sa.rowid
WHERE ba.rowid = b.fk_account AND ba.entity IN (1) and date_format(b.dateo,'%Y%m') = '201901' ORDER BY b.dateo, b.num_releve */

$sql = "SELECT DISTINCT b.rowid as b_rowid, ba.ref as ba_ref, ba.label as ba_label, b.dateo as b_dateo, b.label as b_label, b.num_chq as b_num_chq, b.amount as b_amount, b.emetteur as b_emetteur, 
s.code_compta as s_code_compta, s.nom as s_nom, 
concat(a.firstname, ' ', a.lastname) as a_nom, sa.code_compta as sa_code_compta, sa.nom as sa_nom
FROM (lx_bank_account as ba, lx_bank as b) 
LEFT JOIN lx_bank_url as bu ON (bu.fk_bank = b.rowid AND bu.type = 'company') LEFT JOIN lx_societe as s ON bu.url_id = s.rowid 
LEFT JOIN lx_bank_url as bua ON (bua.fk_bank = b.rowid AND bua.type = 'member') LEFT JOIN lx_adherent as a ON bua.url_id = a.rowid LEFT JOIN lx_societe as sa ON a.fk_soc = sa.rowid 
WHERE ba.rowid = b.fk_account AND ba.entity IN (1) and date_format(b.dateo,'%Y%m') = '$month_export' ORDER BY b.dateo, b.num_releve
";

// TODO Find a better trick to avoid problem with some mysql installations
if (in_array($db->type, array('mysql', 'mysqli'))){
	$db->query('SET SQL_BIG_SELECTS=1');
}

$header = ["Date", "Libelle", "Debit", "Credit", "Code_compta", "No_Piece"];
$releves = [];

$result = $db->query($sql);
if ($result){
	$num = $db->num_rows($result);
	$i = 0;
	while ($i<$num){
		$obj = $db->fetch_object($result);
		$i++;

		$banque = $obj->ba_ref;
		if (!isset($releves[$banque])) {
			$releves[$banque] = [];
		}
		$date = date('d/m/Y',strtotime($obj->b_dateo));
		$piece = "";
		$code_compta = "";

		switch($obj->b_label) {
			case '(CustomerInvoicePayment)':
				$who = trim($obj->s_nom);
				// cut a bit the crap
				$who = preg_replace(",\(.*\)$,Uims", "", $who);
				$who = explode(' - ', $who)[0];
				$who = explode(' / ', $who)[0];
				$libelle = "Regl." . ($who ? ' ' . $who : '');
				break;
			case '(SupplierInvoicePayment)':
				$libelle = "Paiement fournisseur" . ($obj->s_nom ? ': '.$obj->s_nom : '');
				// on ignore les paiements fournisseurs, on veut juste exporter les reglements clients
				continue 2;
				break;
			default:
				$libelle = $obj->b_label;
				if (strpos($libelle, 'fournisseur')) {
					// on ignore les paiements fournisseurs, on veut juste exporter les reglements clients
					continue 2;
				}
				$libelle = preg_replace(",Adh.+sion/Cotisation,Uims", "Adhesion", $libelle);
				if (!$libelle){
					$libelle = "Regl.";
				}
				if ($obj->a_nom) {
					$libelle .= " " . $obj->a_nom;
				} elseif ($obj->s_nom) {
					$libelle .= " " . $obj->s_nom;
				} elseif ($obj->sa_nom) {
					$libelle .= " " . $obj->sa_nom;
				}
				break;
		}

		// code_compta uniquement : pour les adhesion c'est une autre imputation, fixe (on ignore donc sa_code_compta)
		if ($c = $obj->s_code_compta) {
			$code_compta = $c;
			$libelle .= " $code_compta";
		}

		if ($obj->b_num_chq) {
			$piece = $obj->b_num_chq;
			$libelle .= " [$piece]";
		}

		// DEBUG
		//$libelle = $obj->b_rowid .". ".$libelle;

		$debit = '';
		$credit = '';
		if ($obj->b_amount>0) {
			$credit = str_replace(".",",", sprintf("%.2f", $obj->b_amount));
		}
		else {
			$debit = str_replace(".",",", sprintf("%.2f", -$obj->b_amount));
		}
		$releves[$banque][] = [$date, $libelle, $debit, $credit, $code_compta, $piece];
	}
} else {
	die("Erreur: rien a exporter pour la periode : $month_export");
}

// lister les comptes demandes ou tous les comptes bancaires
if (count($comptes)) {
	$c = array_map("addslashes", $comptes);
	$c = "'" . implode("', '", $c) . "'";
	$in_comptes = "AND ba.ref IN ($c)";
}

$sql = "SELECT ba.ref as ba_ref, ba.label as ba_label FROM lx_bank_account as ba WHERE ba.clos = 0 AND ba.entity IN (1) $in_comptes ORDER BY ba.rowid";
$result = $db->query($sql);
if ($result){
	$num = $db->num_rows($result);
	while ($obj = $db->fetch_object($result)){
		$banque = $obj->ba_ref;
		$nom = $obj->ba_label;

		if ($num > 1) {
			echo "\n\n$nom [$banque]\n=======\n";
		}

		if (isset($releves[$banque])) {
			$csv = exporter_csv('', $releves[$banque], ';', $header);
			echo $csv;
		}
		else {
			$csv = exporter_csv('', [], ';', $header);
			echo $csv;
		}
	}
}
else {
	die("Rien pour les comptes ".implode(',', $comptes));
}