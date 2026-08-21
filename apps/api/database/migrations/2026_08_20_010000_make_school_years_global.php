<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Les années scolaires n'ont jamais été réellement isolées par établissement
 * côté application (tous les écrans consommateurs — Dashboard, Classes,
 * Tarifs, Bulletins... — font SchoolYear::where(...) sans filtre
 * establishment_id) : seule la colonne l'était, ce qui produisait un
 * enregistrement dupliqué par établissement pour la même année. On aligne le
 * modèle de données sur l'usage réel : une année scolaire est saisie une
 * seule fois (par un administrateur SaaS, voir SchoolYearPolicy) et partagée
 * par tous les établissements.
 *
 * Les tables qui référencent school_year_id (classrooms, terms, enrollments,
 * level_fees, installments, discounts, teacher_classroom_subject,
 * report_cards) portent chacune leur propre establishment_id — aucune ne
 * dépend de school_years.establishment_id pour son isolation tenant — donc
 * réattribuer leurs lignes vers l'enregistrement fusionné ci-dessous ne
 * risque aucune collision de contrainte unique.
 */
return new class extends Migration
{
    private const CHILD_TABLES = [
        'classrooms',
        'terms',
        'enrollments',
        'level_fees',
        'installments',
        'discounts',
        'teacher_classroom_subject',
        'report_cards',
    ];

    public function up(): void
    {
        $this->mergeDuplicateLabels();
        $this->keepOnlyOneCurrentYear();

        Schema::table('school_years', function (Blueprint $table): void {
            $table->dropIndex(['establishment_id', 'is_current']);
            $table->dropForeign(['establishment_id']);
            $table->dropColumn('establishment_id');
        });

        Schema::table('school_years', function (Blueprint $table): void {
            $table->unique('label');
        });
    }

    public function down(): void
    {
        Schema::table('school_years', function (Blueprint $table): void {
            $table->dropUnique(['school_years_label_unique']);
        });

        Schema::table('school_years', function (Blueprint $table): void {
            $table->foreignId('establishment_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->index(['establishment_id', 'is_current']);
        });
    }

    /**
     * Fusionne les lignes qui partagent le même libellé (un même millésime
     * saisi séparément par plusieurs établissements) en un seul
     * enregistrement, en réattribuant d'abord les références des tables
     * filles vers la ligne conservée.
     */
    private function mergeDuplicateLabels(): void
    {
        $duplicateLabels = DB::table('school_years')
            ->whereNull('deleted_at')
            ->select('label')
            ->groupBy('label')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('label');

        foreach ($duplicateLabels as $label) {
            $rows = DB::table('school_years')
                ->where('label', $label)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get();

            $keepId = $rows->first()->id;
            $duplicateIds = $rows->skip(1)->pluck('id');

            if ($duplicateIds->isEmpty()) {
                continue;
            }

            foreach (self::CHILD_TABLES as $table) {
                DB::table($table)->whereIn('school_year_id', $duplicateIds)->update(['school_year_id' => $keepId]);
            }

            DB::table('school_years')->whereIn('id', $duplicateIds)->delete();
        }
    }

    /**
     * Avant fusion, chaque établissement pouvait marquer sa propre "année
     * courante" — une fois le référentiel partagé, une seule ligne doit
     * rester courante globalement. On garde celle à la date de début la plus
     * récente.
     */
    private function keepOnlyOneCurrentYear(): void
    {
        $current = DB::table('school_years')
            ->whereNull('deleted_at')
            ->where('is_current', true)
            ->orderByDesc('starts_on')
            ->get();

        if ($current->count() <= 1) {
            return;
        }

        $keepId = $current->first()->id;

        DB::table('school_years')
            ->where('is_current', true)
            ->where('id', '!=', $keepId)
            ->update(['is_current' => false]);
    }
};
