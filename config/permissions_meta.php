<?php

/*
 * Human-readable metadata for the permission/role admin UI (French).
 * IMPORTANT: this file lives in config/, NOT app/Permissions/ — the permission
 * seeder globs app/Permissions/*.php and would treat any string here as a real
 * permission name.
 *
 * `groups`  ordered map: French section label => [permission codes]
 * `labels`  permission code => short French phrase
 * `roles`   role name => one-line French description
 */

return [
    'groups' => [
        'Flotte' => [
            'truck-list', 'truck-create', 'truck-edit', 'truck-delete',
            'truck-rest-window-list', 'truck-rest-window-edit',
        ],
        'Conducteurs' => [
            'driver-list', 'driver-create', 'driver-edit', 'driver-delete',
            'driver-discipline-view', 'driver-discipline-manage',
        ],
        'Transport' => [
            'transport-tracking-list', 'transport-tracking-create', 'transport-tracking-edit', 'transport-tracking-delete',
            'provider-list', 'provider-create', 'provider-edit', 'provider-delete',
            'transporter-list', 'transporter-create', 'transporter-edit', 'transporter-delete',
        ],
        'Maintenance' => [
            'maintenance-list', 'maintenance-create', 'maintenance-edit', 'maintenance-delete',
            'maintenance-assign', 'maintenance-approve', 'maintenance-rule-create', 'maintenance-rule-deactivate',
            'rotation-validate',
        ],
        'Inspections & HSE' => [
            'inspection-list', 'inspection-create', 'inspection-edit', 'inspection-delete',
            'inspection-show', 'inspection-validate', 'weekly-checklist-validate', 'checklist-issue-resolve',
        ],
        'Planification' => [
            'fleet-optimization-view', 'fleet-optimization-run',
            'client-demand-list', 'client-demand-create', 'client-demand-edit', 'client-demand-delete',
            'fleet-roster-plan', 'daily-dispatch-list', 'daily-dispatch-edit', 'objective-history-list',
        ],
        'Sécurité' => [
            'fleet-map-view',
        ],
        'Tableaux & rapports' => [
            'logistics-dashboard', 'report-view',
        ],
        'Administration' => [
            'user-list', 'user-show', 'user-create', 'user-edit', 'user-delete',
            'user-change-password', 'user-invitation', 'user-suspend',
            'role-list', 'role-show', 'role-create', 'role-edit', 'role-delete',
            'invitation-list', 'invitation-show', 'invitation-create', 'invitation-edit', 'invitation-delete',
            'audit-log-view', 'fleet-settings-edit', 'fuel-import',
            'entity-list', 'entity-show', 'entity-create', 'entity-edit', 'entity-delete',
            'project-list', 'project-show', 'project-create', 'project-edit', 'project-delete',
            'project-assign-user', 'project-remove-user',
        ],
    ],

    'labels' => [
        'truck-list' => 'Voir les camions',
        'truck-create' => 'Ajouter un camion',
        'truck-edit' => 'Modifier un camion',
        'truck-delete' => 'Supprimer un camion',
        'truck-rest-window-list' => 'Voir les repos camions',
        'truck-rest-window-edit' => 'Gérer les repos camions',

        'driver-list' => 'Voir les conducteurs',
        'driver-create' => 'Ajouter un conducteur',
        'driver-edit' => 'Modifier un conducteur',
        'driver-delete' => 'Supprimer un conducteur',
        'driver-discipline-view' => 'Voir la discipline conducteur',
        'driver-discipline-manage' => 'Gérer la discipline conducteur',

        'transport-tracking-list' => 'Voir le suivi transport',
        'transport-tracking-create' => 'Ajouter une rotation',
        'transport-tracking-edit' => 'Modifier une rotation',
        'transport-tracking-delete' => 'Supprimer une rotation',
        'provider-list' => 'Voir les fournisseurs',
        'provider-create' => 'Ajouter un fournisseur',
        'provider-edit' => 'Modifier un fournisseur',
        'provider-delete' => 'Supprimer un fournisseur',
        'transporter-list' => 'Voir les transporteurs',
        'transporter-create' => 'Ajouter un transporteur',
        'transporter-edit' => 'Modifier un transporteur',
        'transporter-delete' => 'Supprimer un transporteur',

        'maintenance-list' => 'Voir la maintenance',
        'maintenance-create' => 'Enregistrer une maintenance',
        'maintenance-edit' => 'Modifier une maintenance',
        'maintenance-delete' => 'Supprimer une maintenance',
        'maintenance-assign' => 'Assigner une maintenance',
        'maintenance-approve' => 'Signer / approuver une maintenance',
        'maintenance-rule-create' => 'Créer une règle de maintenance',
        'maintenance-rule-deactivate' => 'Désactiver une règle de maintenance',
        'rotation-validate' => 'Valider les rotations',

        'inspection-list' => 'Voir les inspections',
        'inspection-create' => 'Créer une inspection',
        'inspection-edit' => 'Modifier une inspection',
        'inspection-delete' => 'Supprimer une inspection',
        'inspection-show' => 'Consulter une inspection',
        'inspection-validate' => 'Valider une inspection',
        'weekly-checklist-validate' => 'Valider les checklists hebdo',
        'checklist-issue-resolve' => 'Résoudre les problèmes signalés',

        'fleet-optimization-view' => "Voir l'optimisation flotte",
        'fleet-optimization-run' => "Lancer l'optimisation flotte",
        'client-demand-list' => 'Voir les demandes client',
        'client-demand-create' => 'Ajouter une demande client',
        'client-demand-edit' => 'Modifier une demande client',
        'client-demand-delete' => 'Supprimer une demande client',
        'fleet-roster-plan' => 'Planifier la flotte',
        'daily-dispatch-list' => 'Voir la programmation',
        'daily-dispatch-edit' => 'Gérer la programmation',
        'objective-history-list' => "Voir l'historique des objectifs",

        'fleet-map-view' => 'Voir la cartographie flotte',

        'logistics-dashboard' => 'Tableau de bord logistique',
        'report-view' => 'Voir les rapports',

        'user-list' => 'Voir les utilisateurs',
        'user-show' => 'Consulter un utilisateur',
        'user-create' => 'Ajouter un utilisateur',
        'user-edit' => 'Modifier un utilisateur',
        'user-delete' => 'Supprimer un utilisateur',
        'user-change-password' => 'Changer le mot de passe',
        'user-invitation' => 'Inviter un utilisateur',
        'user-suspend' => 'Suspendre un utilisateur',
        'role-list' => 'Voir les rôles',
        'role-show' => 'Consulter un rôle',
        'role-create' => 'Créer un rôle',
        'role-edit' => 'Modifier un rôle',
        'role-delete' => 'Supprimer un rôle',
        'invitation-list' => 'Voir les invitations',
        'invitation-show' => 'Consulter une invitation',
        'invitation-create' => 'Envoyer une invitation',
        'invitation-edit' => 'Modifier une invitation',
        'invitation-delete' => 'Supprimer une invitation',
        'audit-log-view' => "Voir le journal d'activité",
        'fleet-settings-edit' => 'Modifier les paramètres flotte',
        'fuel-import' => 'Importer le carburant',
        'entity-list' => 'Voir les entités',
        'entity-show' => 'Consulter une entité',
        'entity-create' => 'Créer une entité',
        'entity-edit' => 'Modifier une entité',
        'entity-delete' => 'Supprimer une entité',
        'project-list' => 'Voir les projets',
        'project-show' => 'Consulter un projet',
        'project-create' => 'Créer un projet',
        'project-edit' => 'Modifier un projet',
        'project-delete' => 'Supprimer un projet',
        'project-assign-user' => 'Affecter un utilisateur au projet',
        'project-remove-user' => 'Retirer un utilisateur du projet',
    ],

    'roles' => [
        'Super Admin' => "Accès complet à toute l'application.",
        'Admin' => 'Administration complète (sauf création/suppression de rôles).',
        'Manager' => 'Gestion des données logistiques (flotte, transport, fournisseurs).',
        'Driver' => 'Espace conducteur : checklist, signalement, voyages.',
        'Logistics Responsible' => 'Supervision logistique : inspections, validation, maintenance.',
        'HSE Agent' => 'Inspections sécurité (consultation et édition).',
    ],
];
