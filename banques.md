# LES ENCAISSEMENTS

Le fichier exporté doit être au format csv ou txt (séparateur point-virgule ou tabulation). Le nombre de lignes d'en-tête est paramétrable dans MaCompta.
Il ne doit pas contenir le compte de trésorerie de contre-partie (5...), car le traitement passe par un import de "relevé bancaire" et non de "journal de trésorerie".

L'état disponible dans l'outil d'export, [Banques et caisses / Ecritures bancaires et relevés] sous le modèle "Jrn_Bq_2" dans mon utilisateur, peut servir de base.
La requête SQL correspondant est en PJ. Il faut adapter la période (dans l'exemple, j'ai pris tout 2019).
Et il faut faire un fichier pour chaque compte : Crédit Mutuel / Livret Bleu / Stripe. La Caisse n'a pas à être traitée, car pas importable, et saisie manuellement par Adèle (très peu d'écritures). Filtre sur le champs Ref_Compte (cf. screenshot).

(Rq. pour Stripe, le traitement actuel n'est pas un import de relevé, mais un journal d'OD. Je pense que je vais plutôt adapter MaCompta pour simplifier. Je reviens vers toi à ce sujet, le cas échéant, si l'adaptation n'est pas possible. Donc pour l'instant, il faut traiter Stripe comme les autres comptes.)

Le champs "Date opé." doit être au format jj/mm/aaaa ou mm/jj/aaaa.
Les champs "Débit" et "Crédit" semblent corrects, sous réserve de remplacer les points par des virgules dans les montants.
Il faut ajouter un champ "Libellé" qui sera la concaténation des champs "Code comptable client" / "Tiers" / "Chèque/Virement N°"

Les affectations comptables sont réalisés dans MaCompta au moyen d'imputations automatiques paramétrées. Je reviendrai peut-être vers toi pour des adaptations après import du mois de janvier.

Sur les mois de janvier à avril, il y a dans l'export des écritures bancaires, les règlements fournisseurs. Il n'est pas utile de retraiter ce point, car à partir de mai, plus aucune écriture concernant les fournisseurs ne sera saisie dans Dolibarr.


```
SELECT DISTINCT b.rowid as b_rowid, ba.ref as ba_ref, ba.label as ba_label, b.dateo as b_dateo, b.label as b_label, b.num_chq as b_num_chq, -b.amount as _b_amount, b.amount as b_amount, s.nom as s_nom 
FROM (lx_bank_account as ba, lx_bank as b) LEFT JOIN lx_bank_url as bu ON (bu.fk_bank = b.rowid AND bu.type = 'company') LEFT JOIN lx_societe as s ON bu.url_id = s.rowid 
WHERE ba.rowid = b.fk_account AND ba.entity IN (1) and date_format(b.dateo,'%Y%m') = '201904' ORDER BY b.datev, b.num_releve 
```