# Appels liés à l'emploi du temps (secondaire)

*(Validé par l'utilisateur le 2026-09-06.)*

## Contexte

Aujourd'hui, `App\Livewire\Attendance\Sessions\Index::create()`/`save()` fait créer une `AttendanceSession` en saisissant librement classe + matière (nullable) + date + heure de début — aucun lien avec une structure horaire. Depuis le chantier « emplois du temps du secondaire » (`App\Domain\Timetable`), une classe secondaire a maintenant un planning réel (`TimetableSession` = classe + matière + enseignant + jour + créneau + salle, contrainte aux `TeacherAssignment`). Ce chantier relie la prise de présence à ce planning pour les classes secondaires : un enseignant fait l'appel depuis sa séance du jour au lieu de ressaisir classe/matière/heure à la main.

## Périmètre

- Secondaire uniquement (seul cycle couvert par l'emploi du temps). Préscolaire/primaire garde exactement le flux actuel (saisie libre), sans aucun changement de comportement ni d'écran pour ces cycles.
- Le nouveau chemin (« séances du jour ») devient le point d'entrée **principal** pour le secondaire, mais la saisie libre existante reste disponible en secours pour tout cas hors emploi du temps (rattrapage, remplacement imprévu, emploi du temps pas encore à jour) — décision actée : pas de suppression de la souplesse actuelle, ajout d'un raccourci qui la rend inutile dans le cas courant.
- Un enseignant ne voit que ses propres séances du jour ; un admin (directeur/gestionnaire/fondateur — même périmètre que `hasAdminRightsOnCurrentEstablishment()`, déjà utilisé par cet écran pour distinguer vue admin/vue enseignant) voit celles de tout l'établissement, avec le nom de l'enseignant affiché sur chaque ligne (utile pour couvrir un enseignant absent).
- Reclique sur une séance déjà pointée aujourd'hui : pas de doublon, réouverture de l'appel existant en modification.
- Aucun changement de l'écran `Mark` (saisie présence/absence par élève) ni du modèle de conflits de l'emploi du temps.

## 1. Schéma

Nouvelle colonne nullable sur `attendance_sessions`, reliant une occurrence réelle (date précise) à son gabarit hebdomadaire (`TimetableSession`) :

```php
Schema::table('attendance_sessions', function (Blueprint $table): void {
    $table->foreignId('timetable_session_id')->nullable()->after('subject_id')->constrained()->nullOnDelete();
    $table->unique(['timetable_session_id', 'session_date']);
});
```

`nullOnDelete()` (pas `cascadeOnDelete()`) : si la séance d'emploi du temps est supprimée/modifiée plus tard, l'historique de présence déjà enregistré reste intact, juste délié de son origine. La contrainte unique empêche deux `AttendanceSession` pour la même séance planifiée le même jour — MySQL autorise plusieurs lignes `NULL` sur une colonne d'un index unique, donc aucun impact sur les appels en saisie libre (`timetable_session_id` restant `null`) ni sur le préscolaire/primaire.

`App\Domain\Attendance\Models\AttendanceSession` : ajouter `timetable_session_id` à `$fillable` et une relation :

```php
/**
 * @return BelongsTo<TimetableSession, $this>
 */
public function timetableSession(): BelongsTo
{
    return $this->belongsTo(TimetableSession::class);
}
```

## 2. Écran `Attendance\Sessions\Index`

### `render()`

Ajoute le calcul des séances du jour, à côté des données existantes (`sessions`, `classrooms`, `subjects`) :

```php
$todayDayOfWeek = DayOfWeek::tryFrom(strtolower(now()->format('l')));

$todaysTimetableSessions = $todayDayOfWeek === null
    ? collect()
    : TimetableSession::where('day_of_week', $todayDayOfWeek)
        ->when(! $isAdmin, fn ($query) => $query->where('user_id', $user->id))
        ->with(['classroom', 'subject', 'teacher', 'slot'])
        ->get()
        ->sortBy(fn (TimetableSession $s) => $s->slot->sequence);

$attendedTimetableSessionIds = AttendanceSession::query()
    ->where('session_date', now()->toDateString())
    ->whereNotNull('timetable_session_id')
    ->pluck('id', 'timetable_session_id');
```

(`DayOfWeek::tryFrom(...)` renvoie `null` un samedi/dimanche — l'enum ne couvre que lundi-vendredi — la liste est alors simplement vide, comportement cohérent avec le reste du module Timetable.)

Passées à la vue : `todaysTimetableSessions`, `attendedTimetableSessionIds` (map `timetable_session_id => attendance_session_id`, pour savoir si une ligne affiche « Faire l'appel » ou « Modifier l'appel »).

### Nouvelle méthode `startFromTimetable()`

```php
public function startFromTimetable(int $timetableSessionId): void
{
    $this->authorize('create', AttendanceSession::class);

    $timetableSession = TimetableSession::with('slot')->findOrFail($timetableSessionId);

    /** @var User $user */
    $user = Auth::user();

    if (! $user->hasAdminRightsOnCurrentEstablishment() && $timetableSession->user_id !== $user->id) {
        abort(403);
    }

    $session = AttendanceSession::firstOrCreate(
        [
            'timetable_session_id' => $timetableSession->id,
            'session_date' => now()->toDateString(),
        ],
        [
            'classroom_id' => $timetableSession->classroom_id,
            'subject_id' => $timetableSession->subject_id,
            'teacher_id' => $timetableSession->user_id,
            'started_at' => $timetableSession->slot->start_time->format('H:i'),
        ]
    );

    $this->redirectRoute('attendance.sessions.mark', $session);
}
```

Même schéma d'autorisation à deux niveaux que `save()` existant (policy générique `create` + vérification fine d'appartenance) — ici la vérification fine compare directement `timetable_session_id->user_id`, plus simple que l'actuel `isAssignedToClassroom()` puisque la séance porte déjà l'enseignant assigné.

### Vue

Nouvelle section en tête de `livewire/attendance/sessions/index.blade.php`, avant la liste historique existante : **« Mes séances du jour »** (titre **« Séances du jour »** pour un admin, avec une colonne Enseignant supplémentaire). Une ligne par `TimetableSession` de `todaysTimetableSessions` : classe, matière, horaire du créneau (+ enseignant si admin), et un bouton — `wire:click="startFromTimetable({{ $s->id }})"` — libellé **« Faire l'appel »** si `$s->id` absent de `attendedTimetableSessionIds`, sinon **« Modifier l'appel »** (lien direct vers la route `attendance.sessions.mark` avec l'id déjà connu, pas besoin de repasser par la méthode). Section masquée (ou message « Aucune séance planifiée aujourd'hui ») si `todaysTimetableSessions` est vide — cas normal un week-end ou pour un établissement sans classe secondaire. Le bouton existant **« Nouvelle séance »** (saisie libre) reste affiché en dessous, inchangé, toujours utilisable.

## Hors périmètre

- Préscolaire/primaire : aucun changement.
- Écran `Mark` : aucun changement.
- Édition/suppression de séances d'emploi du temps depuis cet écran.
- Notification si une séance planifiée du jour n'a toujours pas d'appel fait en fin de journée — pourrait être une évolution future (rappel/alerte), pas demandée ici.

## Tests

`tests/Feature/Livewire/Attendance/Sessions/IndexTest.php` (extension) :
- Un enseignant secondaire voit uniquement ses propres séances du jour (pas celles d'un collègue), avec le bon jour de la semaine (mock `Carbon::setTestNow` sur un lundi connu).
- Un admin voit toutes les séances du jour de l'établissement, avec le nom de l'enseignant.
- `startFromTimetable()` crée une `AttendanceSession` correctement pré-remplie (classe/matière/enseignant/heure) et redirige vers `Mark`.
- Reclique sur la même séance le même jour : pas de doublon (`firstOrCreate`), redirige vers la même `AttendanceSession`.
- Un enseignant ne peut pas démarrer l'appel sur la séance d'un collègue (`abort(403)`), un admin le peut.
- Une classe préscolaire/primaire n'apparaît jamais dans « séances du jour » (aucune `TimetableSession` possible pour ce cycle) — le flux libre existant reste inchangé pour elle.
- Un samedi/dimanche (mock `Carbon::setTestNow`) : liste vide, pas d'erreur.

`tests/Feature/Domain/Attendance/AttendanceSessionTest.php` (ou factory/migration test existant) : la contrainte unique `(timetable_session_id, session_date)` bloque une deuxième ligne pour la même paire mais autorise plusieurs lignes `timetable_session_id = null` le même jour (saisie libre).

## i18n

`__('Mes séances du jour')`, `__('Séances du jour')`, `__('Faire l\'appel')` (déjà existant, réutilisé), `__('Modifier l\'appel')`, `__('Aucune séance planifiée aujourd\'hui.')` — clés ajoutées à `lang/en.json`/`lang/ar.json`.

## Vérification

1. Bascule sur base SQLite jetable, `migrate:fresh --seed`, suite Pest complète, PHPStan, Pint scopé.
2. Vérification manuelle Playwright : connexion enseignant secondaire ayant au moins une séance du jour (seeder ou données de test dédiées, jamais de compte réel — voir piège documenté dans la mémoire projet), clic « Faire l'appel », vérification du pré-remplissage classe/matière/heure, marquage de quelques élèves, retour sur Présences, reclique sur la même séance → « Modifier l'appel » → mêmes données rechargées. Vérification vue admin (plusieurs enseignants listés). Vérification qu'une classe préscolaire/primaire ne fait apparaître aucune ligne dans « séances du jour ».
3. Aucune migration de données réelles nécessaire (colonne nullable, aucune ligne existante affectée).
4. Commit puis mise à jour de la mémoire projet si un piège notable est rencontré.
