<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Tables exclues de la sauvegarde/restauration
    |--------------------------------------------------------------------------
    |
    | Tables techniques Laravel, sans valeur métier — jamais exportées, jamais
    | vidées, jamais restaurées. Toute autre table de la base est incluse
    | automatiquement (voir App\Domain\Backup\Services\BackupTableRegistry).
    |
    */
    'excluded_tables' => [
        'cache',
        'cache_locks',
        'jobs',
        'failed_jobs',
        'job_batches',
        'sessions',
        'password_reset_tokens',
        'personal_access_tokens',
        'migrations',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables exclues du vidage « Toutes les tables »
    |--------------------------------------------------------------------------
    |
    | Restent exportées/restaurées normalement, mais absentes du vidage en
    | masse — la vider casse la génération d'uid_serveur pour toute nouvelle
    | donnée (compteurs par préfixe, App\Domain\Sync\Services\
    | UidServerAssigner) tant qu'elle n'est pas manuellement reseedée. Reste
    | vidable si explicitement ciblée table par table.
    |
    */
    'wipe_excluded_tables' => [
        'uid_server_counters',
    ],

    /*
    |--------------------------------------------------------------------------
    | Disque et dossiers de travail
    |--------------------------------------------------------------------------
    |
    | Toujours le disque "local" (racine storage/app/private, non public) —
    | ces fichiers contiennent des données sensibles de toute la plateforme.
    |
    */
    'disk' => 'local',
    'staging_directory' => 'backups-tmp',
    'archive_directory' => 'backups',
    'upload_directory' => 'backups-uploads',

    /*
    |--------------------------------------------------------------------------
    | Réglages d'exécution
    |--------------------------------------------------------------------------
    */
    'insert_chunk_size' => 500,

    // En Ko — à ajuster selon upload_max_filesize/post_max_size réels du serveur cible.
    'max_upload_size_kb' => 51200,
];
