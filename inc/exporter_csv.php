<?php

/***************************************************************************\
 *  SPIP, Systeme de publication pour l'internet                           *
 *                                                                         *
 *  Copyright (c) 2001-2019                                                *
 *  Arnaud Martin, Antoine Pitrou, Philippe Riviere, Emmanuel Saint-James  *
 *                                                                         *
 *  Ce programme est un logiciel libre distribue sous licence GNU/GPL.     *
 *  Pour plus de details voir le fichier COPYING.txt ou l'aide en ligne.   *
\***************************************************************************/

/**
 * Gestion d'export de données au format CSV
 *
 * @package SPIP\Core\CSV\Export
 **/

/**
 * Exporter un champ pour un export CSV : pas de retour a la ligne,
 * et echapper les guillements par des doubles guillemets
 *
 * @param string $champ
 * @return string
 */
function exporter_csv_champ($champ) {
	$champ = preg_replace(',[\s]+,ms', ' ', $champ);
	$champ = str_replace('"', '""', $champ);

	return '"' . $champ . '"';
}

/**
 * Exporter une ligne complete au format CSV, avec delimiteur fourni
 *
 * @uses exporter_csv_champ()
 *
 * @param array $ligne
 * @param string $delim
 * @param string|null $importer_charset
 *     Si défini exporte dans le charset indiqué
 * @return string
 */
function exporter_csv_ligne($ligne, $delim = ', ') {
	$output = join($delim, array_map('exporter_csv_champ', $ligne)) . "\r\n";

	return $output;
}

/**
 * Exporte une ressource sous forme de fichier CSV
 *
 * La ressource peut etre un tableau ou une resource SQL issue d'une requete
 * L'extension est choisie en fonction du delimiteur :
 * - si on utilise ',' c'est un vrai csv avec extension csv
 * - si on utilise ';' ou tabulation c'est pour E*cel, et on exporte en iso-truc, avec une extension .xls
 *
 * @uses exporter_csv_ligne()
 *
 * @param string $titre
 *   titre utilise pour nommer le fichier
 * @param array|resource $resource
 * @param string $delim
 *   delimiteur
 * @param array $entetes
 *   tableau d'en-tetes pour nommer les colonnes (genere la premiere ligne)
 * @param bool $envoyer
 *   pour envoyer le fichier exporte (permet le telechargement)
 * @return string
 */
function exporter_csv($filename, $resource, $delim = ', ', $entetes = null) {

	if ($delim == 'TAB') {
		$delim = "\t";
	}
	if (!in_array($delim, array(',', ';', "\t"))) {
		$delim = ',';
	}

	if ($filename) {
		if ($delim == ',') {
			$extension = 'csv';
		} else {
			$extension = 'xls';
		}
		$filename = "$filename.$extension";
	}

	$output = "";
	if ($entetes and is_array($entetes) and count($entetes)) {
		$output .= exporter_csv_ligne($entetes, $delim);
	}
	while ($row = array_shift($resource)) {
		$output .= exporter_csv_ligne($row, $delim);
	}

	if ($filename) {
		file_put_contents($filename, $output);
		return $filename;
	}

	return $output;
}
