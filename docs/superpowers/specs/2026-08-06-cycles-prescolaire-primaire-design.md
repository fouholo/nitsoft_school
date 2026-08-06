# Support des cycles préscolaire et primaire

**Date** : 2026-08-06
**Statut** : Approuvé

## Contexte

L'application ne modélisait explicitement que le secondaire (collège/lycée). Le moteur de notation (`ReportCardService`) calcule une moyenne pondérée par coefficient et un rang — un mécanisme unique, générique, indépendant du niveau. L'exploration du code a confirmé que le schéma (classes, matières, inscriptions) est déjà générique : `classrooms.level` et `establishments.type` sont de simples chaînes libres, sans enum ni table de référence. Le biais "secondaire" ne vit que dans les données de démo/seed (`'6ème'`, `'Terminale'`, `type` = `college`/`lycee`), pas dans le schéma ni la logique métier.

## Décisions validées avec l'utilisateur

1. **Préscolaire** : aucun suivi pédagogique (pas de notes, pas de bulletin) à ce stade — seulement inscription, présence, facturation.
2. **Primaire** : notes chiffrées avec coefficients, exactement le même mécanisme que le secondaire. Les matières et coefficients diffèrent selon le cycle, mais le moteur de calcul est identique — pas de mode "évaluation par compétences" à construire.
3. **Secondaire** : inchangé.
4. **Un établissement peut regrouper plusieurs cycles** : préscolaire + primaire dans un même établissement est un cas réel à supporter. Un établissement peut aussi être uniquement secondaire. Le cycle doit donc être porté par la **classe** (`classroom`), pas par l'établissement — une même école peut avoir des classes des deux cycles.

## Approche retenue

Cycle comme attribut de classe (enum), pilotant uniquement l'accès au module Notes/Bulletins. Alternative écartée : une abstraction "schéma d'évaluation" pluggable par cycle — rejetée car un seul mécanisme d'évaluation (notes/coefficients) est réellement nécessaire aujourd'hui ; le préscolaire n'a pas de mode d'évaluation alternatif, juste aucun. Construire une abstraction sans second cas d'usage réel violerait YAGNI.

## Design

### 1. Modèle de données

- Nouvel enum PHP `App\Domain\Academics\Enums\Cycle` (backed string enum) : `Prescolaire = 'prescolaire'`, `Primaire = 'primaire'`, `Secondaire = 'secondaire'`.
- Nouvelle migration : colonne `cycle` (string, NOT NULL, défaut `'secondaire'`) sur `classrooms`, castée vers l'enum dans le modèle `Classroom`, ajoutée à `$fillable`. Le défaut `'secondaire'` préserve le comportement actuel des données existantes (greenfield, données de démo uniquement — pas de backfill complexe nécessaire).
- `establishments.type` reste un texte libre inchangé (déjà le cas aujourd'hui, déjà non contraint). On ajoute "préscolaire" et "préscolaire-primaire" comme valeurs possibles dans les seeders, à titre purement informatif — le `cycle` de la classe reste la seule source de vérité fonctionnelle. Ce choix permet nativement un établissement mixte sans règle de cohérence supplémentaire à maintenir.
- **Coefficients par cycle** : aucun changement de schéma nécessaire. `subjects.coefficient_default` est déjà par établissement, et `grade_sheets.weight` permet déjà de surcharger le coefficient par évaluation. Une école crée simplement des matières/poids différents pour ses classes de primaire vs de secondaire, comme elle le fait déjà pour n'importe quelle matière.

### 2. Composants impactés

- **Formulaire Classe** (`Livewire\Academics\Classrooms`) : ajout d'un select "Cycle" obligatoire (Préscolaire / Primaire / Secondaire), affiché à côté du `level` texte libre existant.
- **Liste/fiche Classe** : badge affichant le cycle.
- **Module Notes/Bulletins** (GradeSheets, ReportCards, contrôleur PDF bulletin) : un scope `Classroom::gradable()` (ou méthode d'instance `isGradable()`) exclut les classes de cycle préscolaire des sélecteurs de classe. Aucun changement dans `ReportCardService` — il reste générique et sert primaire et secondaire tel quel.
- **Enrollment / Attendance / Billing** : aucun changement, ils s'appliquent déjà uniformément à tous les cycles.
- **Seeders/Factories** : `ClassroomFactory` génère des couples level+cycle cohérents (ex. `'CP'`/primaire, `'Grande Section'`/préscolaire, `'6ème'`/secondaire). Ajout d'un établissement de démo préscolaire+primaire avec des classes des deux cycles, pour couvrir le scénario "établissement mixte" de bout en bout.

### 3. Gestion des erreurs / cas limites

- Classes existantes (données de démo) : cycle par défaut `secondaire` via la migration, comportement inchangé pour tout ce qui est déjà noté.
- Une classe préscolaire n'apparaît jamais dans les sélecteurs de classe du module Notes → en usage normal, personne ne peut tenter de noter un enfant de préscolaire.
- Défense en profondeur (même pattern que la vérification `TeacherAssignment` existante) : une vérification serveur explicite refuse toute tentative directe (URL forgée) de créer/consulter une feuille de notes ou un bulletin pour une classe préscolaire, avec un message métier clair ("Ce niveau n'a pas de notation") plutôt qu'un 403 générique — ce n'est pas un problème de permission mais une règle métier.

### 4. Tests

- Test unitaire : `Classroom::gradable()` / `isGradable()` exclut le cycle préscolaire.
- Test feature : les actions de notation (création de feuille de notes, génération de bulletin) rejettent une classe préscolaire même pour un Admin établissement (règle métier, pas une règle de permission).
- Mise à jour des tests existants si certains dépendaient d'un cycle implicite non explicite.

## Hors périmètre (explicitement écarté)

- Mode d'évaluation par compétences (Acquis/En cours/Non acquis) — non nécessaire, le préscolaire n'a pas de suivi pédagogique et le primaire utilise notes/coefficients comme le secondaire.
- Contrainte de cohérence entre `establishments.type` et les cycles réels de ses classes — laissé libre pour éviter une règle rigide qui pourrait avoir des exceptions.
- Changements aux contrats de synchronisation (`packages/sync-contracts`) — la synchronisation Desktop/Mobile n'est pas encore démarrée (Phase 3 non commencée), la nouvelle colonne sera naturellement incluse quand cette phase débutera.
