<?php
/** Interface uniquement (français). Le contenu vit dans data/content/*.json sous la forme {en,fr,ar}. */
return [
    'brand' => ['tagline' => 'World Five Dimensions'],
    'nav' => [
        'macro' => 'Cinq macro-œuvres', 'services' => '130 services', 'services_unit' => 'services', 'projects' => 'Projets', 'invest' => 'Investir', 'join' => 'Rejoindre',
        'company' => 'Entreprise', 'developers' => 'Développeurs', 'agents' => 'Agents IA', 'pricing' => 'Tarifs', 'menu' => 'Menu',
        'theme' => 'Changer de thème', 'language' => 'Langue', 'skip' => 'Aller au contenu', 'managers' => 'Devenir manager',
        'trust' => 'Confiance et limites', 'investors' => 'Investisseurs', 'partners' => 'Partenaires', 'about' => 'À propos', 'careers' => 'Carrières',
        'faq' => 'FAQ', 'contact' => 'Contact', 'status' => 'Statut', 'how' => 'Comment ça marche', 'ontology' => 'Ontologie',
        'subscription' => 'Abonnement quotidien', 'checkout' => 'Paiement', 'tunisia' => 'Pôle Tunisie',
    ],
    'cta' => [
        'invest' => 'Prendre une part aujourd’hui', 'quote' => 'Voir le prix du jour', 'register' => 'S’inscrire', 'eligibility' => 'Vérifier mon éligibilité',
        'manager' => 'Candidater comme manager', 'dossier' => 'Demander le dossier', 'read_limits' => 'Lire les limites déclarées', 'open' => 'Ouvrir',
        'back_home' => 'Retour à l’accueil', 'contact' => 'Écrire au bon service', 'subscribe' => 'Démarrer un abonnement quotidien', 'next' => 'Suivant',
        'back' => 'Retour', 'confirm' => 'Confirmer', 'send' => 'Envoyer', 'explore' => 'Explorer', 'learn' => 'En savoir plus', 'apply_service' => 'Candidater à ce service',
        'view_project' => 'Voir le projet', 'compare' => 'Comparer', 'download' => 'Télécharger', 'copy' => 'Copier',
    ],
    'limits' => [
        'title' => 'Limites déclarées',
        'jobs' => 'Une part est un investissement dans un parcours, pas un emploi. W5D ne promet aucun emploi.',
        'design' => '« {{ d.experts_target }} experts » est une équation de conception ({{ counts.macros }} × {{ experts.services_per_macro }} × {{ experts.per_service }}), pas un effectif. Experts certifiés aujourd’hui : {{ d.experts_current }}.',
        'returns' => 'Aucun rendement, dividende ni valeur de revente n’est promis. Les parts financent un programme publié ; la fonction de prix est publique.',
        'link' => '/trust/statement/',
    ],
    'footer' => [
        'license' => 'Tableau de bord W5D sous licence à {{ money.license_hour }} TND/heure de travail. Offre promotionnelle valable avant {{ dates.offer_year }}.',
        'rights' => 'W5D TECH. Chiffres au {{ dates.as_of }}.',
        'legal' => 'Mentions', 'privacy' => 'Confidentialité', 'terms' => 'Conditions', 'security' => 'Sécurité',
    ],
    'price' => [
        'per_day' => 'par part et par jour', 'per_week' => 'par part et par semaine', 'progress' => 'Avancement du programme', 'time' => 'Temps écoulé',
        'cap' => 'Plafond', 'base' => 'Base', 'today' => 'Aujourd’hui', 'formula' => 'prix = base × (1 + α·p^k) × (1 + β·t^m), plafonné',
        'live' => 'Calculé en direct par la fonction publiée', 'at_p0' => 'Prix au départ', 'at_p1' => 'Prix au plafond',
    ],
    'lbl' => [
        'name' => 'Nom complet', 'email' => 'E-mail', 'phone' => 'Téléphone', 'org' => 'Organisation', 'message' => 'Message', 'subject' => 'Objet',
        'desk' => 'Service', 'macro' => 'Macro-œuvre', 'service' => 'Service', 'region' => 'Gouvernorat', 'years' => 'Années d’expérience',
        'team' => 'Plus grande équipe dirigée', 'profile' => 'URL du profil professionnel', 'motivation' => 'Pourquoi ce rôle ?', 'scenario' => 'Réponse au scénario',
        'start' => 'Date de début', 'parts' => 'Parts par jour', 'days' => 'Nombre de jours', 'cadence' => 'Cadence', 'daily' => 'Quotidienne', 'weekly' => 'Hebdomadaire',
        'kind' => 'Type d’investisseur', 'individual' => 'Particulier', 'company' => 'Entreprise', 'diploma' => 'Niveau de diplôme', 'field' => 'Domaine d’études',
        'age' => 'Tranche d’âge', 'accept' => 'J’ai lu les limites déclarées', 'required' => 'Obligatoire', 'optional' => 'Facultatif', 'any' => 'Indifférent',
        'step_details' => 'Coordonnées', 'step_parts' => 'Parts', 'step_review' => 'Vérification',
        'step' => 'Étape', 'of' => 'sur', 'total' => 'Total', 'unit' => 'Prix unitaire', 'schedule' => 'Calendrier quotidien', 'date' => 'Date', 'amount' => 'Montant',
        'status' => 'Statut', 'ref' => 'Référence', 'token' => 'Clé de gestion', 'pause' => 'Suspendre', 'resume' => 'Reprendre', 'cancel' => 'Annuler',
        'manage' => 'Gérer', 'search' => 'Rechercher', 'save' => 'Enregistrer', 'none' => 'Rien pour l’instant.',
    ],
    'msg' => [
        'thanks' => 'Merci. Votre demande est enregistrée.', 'error' => 'Veuillez corriger les champs signalés.', 'csrf' => 'Session expirée. Rechargez la page et réessayez.',
        'rate' => 'Trop de tentatives. Réessayez plus tard.', 'demo' => 'Mode démo : aucun paiement n’est prélevé. Votre engagement est enregistré, aucun argent ne circule.',
        'dup_email' => 'Cet e-mail est déjà enregistré.', 'invalid_email' => 'Saisissez une adresse e-mail valide.', 'not_found' => 'Page introuvable.',
    ],
    'admin' => [
        'title' => 'Portail du propriétaire', 'login' => 'Connexion', 'logout' => 'Déconnexion', 'user' => 'Identifiant', 'pass' => 'Mot de passe', 'forbidden' => 'Vous n’avez pas accès à ce menu.',
        'country' => 'Pays', 'rows' => 'lignes', 'page' => 'Page', 'publish' => 'Publier', 'demo_data' => 'Charger des données de démo', 'read_only' => 'Aperçu en lecture seule',
    ],
];
