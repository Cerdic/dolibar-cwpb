#!/usr/bin/php
<?php

$email_dest = "";

$first_day_of_month = date('Y-m-01 00:00:00');

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
}

$month_export = date('Y-m', strtotime($first_day_of_month)-7200);

require __DIR__ . '/doli_boot.php';


$exports = [];

// creer le repertoire exports
$dir = __DIR__ ;
$dir_export = __DIR__ . '/csv';

if (!is_dir($dir_export)) {
	mkdir($dir_export);
}
if (!is_file($f = $dir_export . '/.htaccess')) {
	file_put_contents($f, "deny from all\n");
}

// generer les exports
$file_export = "$dir_export/$month_export-ventes.csv";
$titre_export = "Ventes $month_export";
$exports[$file_export] = $titre_export;
echo "$titre_export -> $file_export\n";
passthru("php $dir/ventes.php $month_export > $file_export");

$sql = "SELECT ba.ref as ba_ref, ba.label as ba_label FROM lx_bank_account as ba WHERE ba.clos = 0 AND ba.entity IN (1) AND ba.ref !='CS' ORDER BY ba.rowid";
$result = $db->query($sql);
if ($result){
	while ($obj = $db->fetch_object($result)){
		$banque = $obj->ba_ref;
		$nom = $obj->ba_label;
		$file_export = "$dir_export/$month_export-banque-{$banque}.csv";
		$titre_export = "Banque $nom [{$banque}] $month_export";
		$exports[$file_export] = $titre_export;
		echo "$titre_export -> $file_export\n";
		passthru("php $dir/banques.php $month_export $banque > $file_export");
	}
}

if ($email_dest) {
	require __DIR__ . '/inc/class.phpmailer.php';

		$destinataire = $email_dest;
		$sujet = "Exports mensuels DOLIBAR";
		$texte = "Exports ci-joints :\n";
		$pieces_jointes = [];
		foreach ($exports as $file => $titre) {
			$texte .= "* $titre : " . basename($file)."\n";
			$pieces_jointes[] = [
				'chemin' => $file,
				'nom' => basename($file),
				'encodage' => 'base64',
				'mime' => 'text/plain',
			];
		}

		$mailer = new PHPMailer();
		$mailer->From = 'noreply@coworking-pb.com';
		$mailer->CharSet = "utf-8";
		$mailer->Mailer = 'mail';
		$mailer->Subject = $sujet;
		$mailer->isHTML(false);
		$mailer->Body = $texte;
		$mailer->addAddress($destinataire);

		foreach ($pieces_jointes as $piece) {
			$mailer->addAttachment(
				$piece['chemin'],
				$piece['nom'],
				$piece['encodage'],
				$piece['mime']
			);
		}

		$mailer->createHeader();
		$mailer->Send();
		echo "Mail envoye a $destinataire\n";
}