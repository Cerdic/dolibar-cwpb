# LES ENCAISSEMENTS

Le fichier export� doit �tre au format csv ou txt (s�parateur point-virgule ou tabulation). Le nombre de lignes d'en-t�te est param�trable dans MaCompta.
Il ne doit pas contenir le compte de tr�sorerie de contre-partie (5...), car le traitement passe par un import de "relev� bancaire" et non de "journal de tr�sorerie".

L'�tat disponible dans l'outil d'export, [Banques et caisses / Ecritures bancaires et relev�s] sous le mod�le "Jrn_Bq_2" dans mon utilisateur, peut servir de base.
La requ�te SQL correspondant est en PJ. Il faut adapter la p�riode (dans l'exemple, j'ai pris tout 2019).
Et il faut faire un fichier pour chaque compte : Cr�dit Mutuel / Livret Bleu / Stripe. La Caisse n'a pas � �tre trait�e, car pas importable, et saisie manuellement par Ad�le (tr�s peu d'�critures). Filtre sur le champs Ref_Compte (cf. screenshot).

(Rq. pour Stripe, le traitement actuel n'est pas un import de relev�, mais un journal d'OD. Je pense que je vais plut�t adapter MaCompta pour simplifier. Je reviens vers toi � ce sujet, le cas �ch�ant, si l'adaptation n'est pas possible. Donc pour l'instant, il faut traiter Stripe comme les autres comptes.)

Le champs "Date op�." doit �tre au format jj/mm/aaaa ou mm/jj/aaaa.
Les champs "D�bit" et "Cr�dit" semblent corrects, sous r�serve de remplacer les points par des virgules dans les montants.
Il faut ajouter un champ "Libell�" qui sera la concat�nation des champs "Code comptable client" / "Tiers" / "Ch�que/Virement N�"

Les affectations comptables sont r�alis�s dans MaCompta au moyen d'imputations automatiques param�tr�es. Je reviendrai peut-�tre vers toi pour des adaptations apr�s import du mois de janvier.

Sur les mois de janvier � avril, il y a dans l'export des �critures bancaires, les r�glements fournisseurs. Il n'est pas utile de retraiter ce point, car � partir de mai, plus aucune �criture concernant les fournisseurs ne sera saisie dans Dolibarr.


```
SELECT DISTINCT b.rowid as b_rowid, ba.ref as ba_ref, ba.label as ba_label, b.dateo as b_dateo, b.label as b_label, b.num_chq as b_num_chq, -b.amount as _b_amount, b.amount as b_amount, s.nom as s_nom 
FROM (lx_bank_account as ba, lx_bank as b) LEFT JOIN lx_bank_url as bu ON (bu.fk_bank = b.rowid AND bu.type = 'company') LEFT JOIN lx_societe as s ON bu.url_id = s.rowid 
WHERE ba.rowid = b.fk_account AND ba.entity IN (1) and date_format(b.dateo,'%Y%m') = '201904' ORDER BY b.datev, b.num_releve 
```