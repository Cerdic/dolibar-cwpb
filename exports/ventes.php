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

$date_startmonth = date('m', $time_start);
$date_startday = date('d', $time_start);
$date_startyear = date('Y', $time_start);
$date_endmonth = date('m', $time_end);
$date_endday = date('d', $time_end);
$date_endyear = date('Y', $time_end);


/* Copyright (C) 2007-2010	Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2007-2010	Jean Heimburger		<jean@tiaris.info>
 * Copyright (C) 2011-2014	Juanjo Menent		<jmenent@2byte.es>
 * Copyright (C) 2012		Regis Houssin		<regis.houssin@capnetworks.com>
 * Copyright (C) 2011-2012  Alexandre Spangaro	<aspangaro.dolibarr@gmail.com>
 * Copyright (C) 2012       Cédric Salvador     <csalvador@gpcsolutions.fr>
 * Copyright (C) 2013		Marcos García		<marcosgdf@gmail.com>
 * Copyright (C) 2014       Raphaël Doursenaud  <rdoursenaud@gpcsolutions.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *    \file       htdocs/compta/journal/sellsjournal.php
 *    \ingroup    societe, facture
 *    \brief      Page with sells journal
 */
global $mysoc;

require __DIR__ . '/../doli_boot.php';
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

$morequery = '&date_startyear=' . $date_startyear . '&date_startmonth=' . $date_startmonth . '&date_startday=' . $date_startday . '&date_endyear=' . $date_endyear . '&date_endmonth=' . $date_endmonth . '&date_endday=' . $date_endday;

$date_start = $time_start;
$date_end = $time_end;

$periodlink = '';
$exportlink = '';
$builddate = dol_now();

$p = explode(":", $conf->global->MAIN_INFO_SOCIETE_COUNTRY);
$idpays = $p[0];

$sql = "SELECT f.rowid, f.facnumber, f.type, f.datef, f.ref_client,";
$sql .= " fd.product_type, fd.total_ht, fd.total_tva, fd.tva_tx, fd.total_ttc, fd.localtax1_tx, fd.localtax2_tx, fd.total_localtax1, fd.total_localtax2, fd.rowid as id, fd.situation_percent,";
$sql .= " s.rowid as socid, s.nom as name, s.code_compta, s.client,";
$sql .= " p.rowid as pid, p.ref as pref, p.accountancy_code_sell,";
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

$result = $db->query($sql);
if ($result){
	$tabfac = array();
	$tabht = array();
	$tabtva = array();
	$tablocaltax1 = array();
	$tablocaltax2 = array();
	$tabttc = array();
	$tabcompany = array();
	$account_localtax1 = 0;
	$account_localtax2 = 0;

	$num = $db->num_rows($result);
	$i = 0;
	$resligne = array();
	while ($i<$num){
		$obj = $db->fetch_object($result);
		// les variables
		$cptcli = (!empty($conf->global->ACCOUNTING_ACCOUNT_CUSTOMER) ? $conf->global->ACCOUNTING_ACCOUNT_CUSTOMER : $langs->trans("CodeNotDef"));
		$compta_soc = (!empty($obj->code_compta) ? $obj->code_compta : $cptcli);
		$compta_prod = $obj->accountancy_code_sell;
		if (empty($compta_prod)){
			if ($obj->product_type==0){
				$compta_prod = (!empty($conf->global->ACCOUNTING_PRODUCT_SOLD_ACCOUNT) ? $conf->global->ACCOUNTING_PRODUCT_SOLD_ACCOUNT : $langs->trans("CodeNotDef"));
			} else {
				$compta_prod = (!empty($conf->global->ACCOUNTING_SERVICE_SOLD_ACCOUNT) ? $conf->global->ACCOUNTING_SERVICE_SOLD_ACCOUNT : $langs->trans("CodeNotDef"));
			}
		}
		$cpttva = (!empty($conf->global->ACCOUNTING_VAT_SOLD_ACCOUNT) ? $conf->global->ACCOUNTING_VAT_SOLD_ACCOUNT : $langs->trans("CodeNotDef"));
		$compta_tva = (!empty($obj->account_tva) ? $obj->account_tva : $cpttva);

		$account_localtax1 = getLocalTaxesFromRate($obj->tva_tx, 1, $obj->thirdparty, $mysoc);
		$compta_localtax1 = (!empty($account_localtax1[3]) ? $account_localtax1[3] : $langs->trans("CodeNotDef"));
		$account_localtax2 = getLocalTaxesFromRate($obj->tva_tx, 2, $obj->thirdparty, $mysoc);
		$compta_localtax2 = (!empty($account_localtax2[3]) ? $account_localtax2[3] : $langs->trans("CodeNotDef"));

		// Situation invoices handling
		$line = new FactureLigne($db);
		$line->fetch($obj->id);   // id of line
		$prev_progress = 0;
		if ($obj->type==Facture::TYPE_SITUATION){
			// Avoid divide by 0
			if ($obj->situation_percent==0){
				$situation_ratio = 0;
			} else {
				$prev_progress = $line->get_prev_progress($obj->rowid);   // id on invoice
				$situation_ratio = ($obj->situation_percent-$prev_progress)/$obj->situation_percent;
			}
		} else {
			$situation_ratio = 1;
		}

		//la ligne facture
		$tabfac[$obj->rowid]["date"] = $obj->datef;
		$tabfac[$obj->rowid]["ref"] = $obj->facnumber;
		$tabfac[$obj->rowid]["type"] = $obj->type;
		if (!isset($tabttc[$obj->rowid][$compta_soc])){
			$tabttc[$obj->rowid][$compta_soc] = 0;
		}
		if (!isset($tabht[$obj->rowid][$compta_prod])){
			$tabht[$obj->rowid][$compta_prod] = 0;
		}
		if (!isset($tabtva[$obj->rowid][$compta_tva])){
			$tabtva[$obj->rowid][$compta_tva] = 0;
		}
		if (!isset($tablocaltax1[$obj->rowid][$compta_localtax1])){
			$tablocaltax1[$obj->rowid][$compta_localtax1] = 0;
		}
		if (!isset($tablocaltax2[$obj->rowid][$compta_localtax2])){
			$tablocaltax2[$obj->rowid][$compta_localtax2] = 0;
		}
		$tabttc[$obj->rowid][$compta_soc] += $obj->total_ttc*$situation_ratio;
		$tabht[$obj->rowid][$compta_prod] += $obj->total_ht*$situation_ratio;
		if ($obj->recuperableonly!=1){
			$tabtva[$obj->rowid][$compta_tva] += $obj->total_tva*$situation_ratio;
		}
		$tablocaltax1[$obj->rowid][$compta_localtax1] += $obj->total_localtax1;
		$tablocaltax2[$obj->rowid][$compta_localtax2] += $obj->total_localtax2;
		$tabcompany[$obj->rowid] = array('id' => $obj->socid, 'name' => $obj->name, 'client' => $obj->client);
		$i++;
	}
} else {
	die("Erreur: rien a exporter pour la periode : " . date('Y-m-d', $date_start) . '-' . date('Y-m-d', $date_end));
}


/*
 * Prepare result array
 */

$export = [];
$header = ["Date", "Compte", "Libelle", "Debit", "Credit", "Piece"];

$invoicestatic = new Facture($db);
$companystatic = new Client($db);

foreach ($tabfac as $key => $val){
	$invoicestatic->id = $key;
	$invoicestatic->ref = $val["ref"];
	$invoicestatic->type = $val["type"];

	//$companystatic->id=$tabcompany[$key]['id'];
	//$companystatic->name=$tabcompany[$key]['name'];
	//$companystatic->client=$tabcompany[$key]['client'];

	$piece = $val["ref"];
	$libelle = $tabcompany[$key]['name'];

	$lines = array(
		array(
			'var' => $tabttc[$key],
			'label' => $libelle,
			'nomtcheck' => true,
			'inv' => true
		),
		array(
			'var' => $tabht[$key],
			'label' => $langs->trans('Products'),
		),
		array(
			'var' => $tabtva[$key],
			'label' => $langs->trans('VAT')
		),
		array(
			'var' => $tablocaltax1[$key],
			'label' => $langs->transcountry('LT1', $mysoc->country_code)
		),
		array(
			'var' => $tablocaltax2[$key],
			'label' => $langs->transcountry('LT2', $mysoc->country_code)
		)
	);

	foreach ($lines as $line){
		foreach ($line['var'] as $k => $mt){
			if (isset($line['nomtcheck']) || $mt){
				$date = date('d/m/Y', strtotime($val['date']));
				$compte = $k;
				if (strlen($compte) < 6) {
					$compte = str_pad($compte, 6, '0', STR_PAD_RIGHT);
				}

				$mtfmt = sprintf("%.2f", $mt);
				$mtfmtneg = sprintf("%.2f", -$mt);
				if (isset($line['inv'])){
					$debit = ($mt>=0 ? $mtfmt : '');
					$credit = ($mt<0 ? $mtfmtneg : '');
				} else {
					$debit = ($mt<0 ? $mtfmtneg : '');
					$credit = ($mt>=0 ? $mtfmt : '');
				}

				$debit = str_replace(".",",", $debit);
				$credit = str_replace(".",",", $credit);

				$export[] = [$date, $compte, $libelle, $debit, $credit, $piece];

			}
		}
	}
}


$csv = exporter_csv('', $export, ',', $header);

echo $csv;