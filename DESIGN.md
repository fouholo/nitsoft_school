---
name: Nitsoft School
description: SaaS de gestion scolaire pour la Côte d'Ivoire — chaleureux, digne de confiance, précis
colors:
  terracotta-institutionnel: "#c2410c"
  terracotta-institutionnel-fonce: "#9a3412"
  terracotta-institutionnel-vif: "#ea580c"
  terracotta-institutionnel-teinte: "#fff7ed"
  terracotta-institutionnel-doux: "#ffedd5"
  terracotta-institutionnel-bordure: "#fed7aa"
  gris-pierre-encre: "#1c1917"
  gris-pierre-corps: "#57534e"
  gris-pierre-discret: "#78716c"
  gris-pierre-passif: "#a8a29e"
  gris-pierre-bordure: "#d6d3d1"
  gris-pierre-trait: "#e7e5e4"
  gris-pierre-fond-alt: "#f5f5f4"
  gris-pierre-fond: "#fafaf9"
  blanc-surface: "#ffffff"
  succes-fond: "#d1fae5"
  succes-texte: "#047857"
  attention-fond: "#fef3c7"
  attention-texte: "#b45309"
  danger-fond: "#fee2e2"
  danger-texte: "#b91c1c"
  danger-lien: "#ef4444"
  cycle-primaire-fond: "#e0f2fe"
  cycle-primaire-texte: "#0369a1"
typography:
  title:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 600
    lineHeight: 1.33
    letterSpacing: "normal"
  headline:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 600
    lineHeight: 1.56
    letterSpacing: "normal"
  body:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.43
    letterSpacing: "normal"
  label:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 500
    lineHeight: 1.33
    letterSpacing: "0.025em"
rounded:
  sm: "4px"
  md: "6px"
  lg: "8px"
  xl: "12px"
  2xl: "16px"
  full: "9999px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "16px"
  lg: "20px"
  xl: "24px"
components:
  button-primary:
    backgroundColor: "{colors.terracotta-institutionnel}"
    textColor: "{colors.blanc-surface}"
    rounded: "{rounded.lg}"
    padding: "6px 12px"
  button-primary-hover:
    backgroundColor: "{colors.terracotta-institutionnel-fonce}"
    textColor: "{colors.blanc-surface}"
    rounded: "{rounded.lg}"
    padding: "6px 12px"
  button-secondary:
    backgroundColor: "{colors.blanc-surface}"
    textColor: "{colors.gris-pierre-corps}"
    rounded: "{rounded.lg}"
    padding: "6px 12px"
  input:
    backgroundColor: "{colors.blanc-surface}"
    textColor: "{colors.gris-pierre-encre}"
    rounded: "{rounded.lg}"
    padding: "6px 12px"
  card:
    backgroundColor: "{colors.blanc-surface}"
    rounded: "{rounded.lg}"
    padding: "16px"
  stat-card:
    backgroundColor: "{colors.blanc-surface}"
    rounded: "{rounded.2xl}"
    padding: "20px"
  badge-success:
    backgroundColor: "{colors.succes-fond}"
    textColor: "{colors.succes-texte}"
    rounded: "{rounded.full}"
    padding: "2px 8px"
---

# Design System: Nitsoft School

## Overview

**Creative North Star: "Le Bureau du Directeur"**

Nitsoft School emprunte le langage visuel du bureau d'un chef d'établissement sérieux mais accessible : des documents propres, une hiérarchie claire, une seule couleur d'accent utilisée avec parcimonie pour signaler l'action, et un fond largement neutre qui laisse la donnée — élèves, notes, factures — occuper l'écran. L'identité a délibérément quitté le registre froid d'un accent bleu-indigo et de gris ardoise bleutés : l'accent est aujourd'hui un terracotta chaud et les neutres sont des gris pierre, pour qu'un directeur, un caissier ou un parent qui s'y connecte pour la première fois ressente un outil humain plutôt qu'un back-office générique — sans jamais verser dans l'exubérance ou le ludique.

Le système est aujourd'hui entièrement porté par la palette par défaut de Tailwind CSS v4 (aucune personnalisation de couleur dans `@theme`, seulement des classes `orange-*` et `stone-*` du référentiel standard) — seule la police (Instrument Sans) a été substituée à la pile système par défaut. Il n'y a pas de logo dessiné : la marque sidebar est aujourd'hui un simple monogramme « N » sur fond terracotta institutionnel, à traiter comme un placeholder plutôt que comme un choix d'identité définitif.

**Key Characteristics:**
- Une seule couleur d'accent (terracotta institutionnel), jamais en fond de page, réservée à l'action et à l'état actif.
- Surfaces plates et bordées ; aucune ombre portée sur les écrans de travail — l'ombre est réservée aux éléments flottants (menus) et aux écrans d'entrée (connexion/inscription).
- Une seule famille de police, hiérarchie construite uniquement par taille et graisse (pas de police display séparée).
- Densité élevée assumée : texte à 14px (`text-sm`) dominant sur la quasi-totalité de l'interface, cohérent avec un outil de travail administratif consulté toute la journée.

## Colors

La palette est délibérément restreinte : un accent, une base neutre chaude, et une poignée de couleurs de statut sémantiques.

### Primary
- **Terracotta Institutionnel** (`#c2410c`) : boutons d'action primaire, lien actif de la navigation, focus des éléments interactifs. Utilisé sur moins de 10 % de la surface d'un écran donné — jamais en fond de section.
- **Terracotta Institutionnel Foncé** (`#9a3412`) : état hover du terracotta institutionnel.
- **Terracotta Institutionnel Vif** (`#ea580c`) : anneau de focus des champs et boutons (plus saturé, pour rester visible sur fond clair sans alourdir l'accent principal).
- **Terracotta Institutionnel Teinte** (`#fff7ed`) : fond du lien de navigation actif dans la sidebar, fond des bannières informatives.
- **Terracotta Institutionnel Doux** (`#ffedd5`) : fond du badge avatar utilisateur, badges informatifs mineurs.
- **Terracotta Institutionnel Bordure** (`#fed7aa`) : bordure des bannières et encadrés utilisant l'accent.

### Neutral
- **Gris Pierre Encre** (`#1c1917`) : titres de page, texte principal.
- **Gris Pierre Corps** (`#57534e`) : liens de navigation au repos, texte secondaire.
- **Gris Pierre Discret** (`#78716c`) : texte méta (sous-titres, légendes de formulaire).
- **Gris Pierre Passif** (`#a8a29e`) : icônes et libellés de très faible priorité.
- **Gris Pierre Bordure** (`#d6d3d1`) : bordure des champs de formulaire.
- **Gris Pierre Trait** (`#e7e5e4`) : bordure des cartes, séparateurs de tableau, ligne sous la sidebar/le header.
- **Gris Pierre Fond Alt** (`#f5f5f4`) : fond d'en-tête de tableau, fond au survol d'une ligne de nav.
- **Gris Pierre Fond** (`#fafaf9`) : fond de page.
- **Blanc Surface** (`#ffffff`) : sidebar, cartes, champs de formulaire.

### Named Rules
**La règle du Fond Neutre.** Le fond de page est toujours gris pierre (`#fafaf9`) ou blanc ; le terracotta institutionnel n'apparaît jamais en arrière-plan d'une zone de contenu, uniquement sur des éléments d'action ou d'état ponctuels.

## Typography

**Body Font:** Instrument Sans (avec repli `ui-sans-serif, system-ui, sans-serif`)

**Character:** Une seule famille pour toute l'interface — la hiérarchie vient exclusivement de la taille et de la graisse, jamais d'un changement de police. Cohérent avec l'esprit outil-de-travail plutôt que produit éditorial.

### Hierarchy
- **Title** (600, 24px/1.33) : titre de page (`<h1>` de chaque écran), en gris pierre encre.
- **Headline** (600, 18px/1.56) : titre de sous-section à l'intérieur d'une page (ex. « Tranches », « Tarifs par niveau ») — utilisé avec parcimonie, la plupart des écrans n'ont qu'un seul niveau de titre.
- **Body** (400–500, 14px/1.43) : taille dominante — libellés de formulaire, cellules de tableau, boutons, texte de navigation. Plus de 700 occurrences dans le code : c'est la taille par défaut de facto de tout le produit.
- **Label** (500, 12px/1.33, `letter-spacing: 0.025em`) : méta-texte, badges de statut, libellés de section en majuscules (`uppercase tracking-wide`, ex. « ACCÈS RAPIDES »).

### Named Rules
**La règle Une Seule Police.** Aucune police display ou serif n'est introduite, même pour les grands titres — la distinction se fait uniquement par `font-size`/`font-weight`.

## Layout

Deux gabarits de page coexistent, tous deux à colonne unique de contenu centré :

- **Écran interne (`layouts.app`)** : sidebar fixe de 256px (`w-64`) à gauche, fond blanc, bordée à droite (`border-stone-200`) ; header de 64px avec le sélecteur d'établissement ; zone de contenu centrée avec un plafond de largeur `max-w-6xl`, marge intérieure `px-6 py-8`.
- **Portail parents (`layouts.guardian-portal`)** : pas de sidebar — une barre de navigation horizontale fine en haut, contenu centré `max-w-3xl` (plus étroit que l'écran interne, cohérent avec un usage occasionnel plutôt qu'une console de travail).

Le rythme vertical entre sections d'une même page suit l'échelle d'espacement (`mt-4` / `mt-6` / `mt-8` / `mt-10`), toujours en escalade croissante à mesure que la distance sémantique entre deux blocs augmente. Les grilles de cartes (tableau de bord, formulaires multi-champs) utilisent `grid-cols-1` en mobile, s'élargissant à 2–4 colonnes selon le contenu (`sm:`/`lg:`).

## Elevation & Depth

Le système est **plat par défaut** : aucune ombre sur les cartes, tableaux ou formulaires des écrans de travail — la séparation vient uniquement de bordures fines (`border-stone-200`) et de fonds contrastés (blanc sur gris pierre). L'ombre n'apparaît que pour deux cas structurels précis : les éléments qui se détachent physiquement du flux (menu déroulant du sélecteur d'établissement) et les écrans d'entrée avant authentification (connexion, inscription).

### Shadow Vocabulary
- **Flottant** (`box-shadow` Tailwind `shadow-lg`) : menus déroulants au-dessus du contenu (ex. sélecteur d'établissement).
- **Entrée** (`box-shadow` Tailwind `shadow-sm`) : champs de formulaire des écrans de connexion/inscription — toujours une dérive du gabarit par défaut de Laravel plutôt qu'une intention délibérée (voir Don't ci-dessous), non touchée par le réchauffement de palette et toujours à corriger avant de la traiter comme une règle.

### Named Rules
**La règle Plat-au-Repos.** Une surface ne porte une ombre que si elle est physiquement détachée du flux normal de la page (menu, popover) — jamais une carte ou un conteneur de contenu statique.

## Shapes

Les coins sont ouverts et accueillants, sans devenir enfantins : `rounded-lg` (8px) domine très largement pour boutons, champs, cartes de liste et conteneurs de formulaire — un cran plus ouvert que le `rounded-md` (6px) d'origine, qui sert désormais de repli mineur plutôt que de défaut. `rounded-xl` (12px) est réservé aux éléments de navigation interactifs (liens de sidebar, monogramme de marque, menu déroulant). `rounded-2xl` (16px) marque les cartes du tableau de bord (statistiques, accès rapides) — un cran plus doux pour les distinguer des écrans de gestion CRUD. `rounded-full` habille systématiquement badges de statut et avatars.

### Named Rules
**La règle du Cran Supplémentaire.** Le tableau de bord (`rounded-2xl`) est visuellement un cran plus doux que les écrans de gestion (`rounded-lg`) — cette différence marque intentionnellement la bascule entre « vue d'ensemble » et « outil de travail ».

## Components

### Buttons
- **Shape:** coins à 8px (`rounded-lg`).
- **Primary:** fond terracotta institutionnel, texte blanc, `padding: 6px 12px`, `font-weight: 500`, `font-size: 14px`. Hover : fond terracotta institutionnel foncé (`#9a3412`).
- **Secondary/Ghost:** fond blanc ou transparent, texte gris pierre corps, bordure `1px solid` gris pierre bordure, même padding que le primary. Utilisé pour « Annuler » à côté de chaque « Enregistrer ».
- **Danger (texte seul) :** pas de fond ; texte `text-red-500`, devient `text-red-700` au survol. Utilisé pour les actions de suppression dans les tableaux — jamais un bouton plein rouge.
- **Confirmation destructive :** les suppressions passent par la confirmation native du navigateur (`wire:confirm`), pas par une modale personnalisée — aucune modale n'existe dans le produit à ce jour.

### Chips / Badges
- **Statut booléen (actif/inactif) :** fond `succes-fond` / texte `succes-texte` si actif, fond `gris-pierre-fond-alt` / texte `gris-pierre-discret` si inactif. Forme `rounded-full`, `padding: 2px 8px`, `text-xs font-medium`.
- **Statut multi-état (factures, présences, SMS) :** même forme, couleur choisie par un `match`/tableau de correspondance sémantique — vert/émeraude = positif (payé, présent), ambre = intermédiaire (partiellement payé, en retard justifié), rouge = négatif (en retard, absent, échec).
- **Badge de cycle scolaire :** ambre = Préscolaire, **Cycle Primaire** (fond `#e0f2fe`, texte `#0369a1`) = Primaire, gris pierre = Secondaire — seul endroit où le terracotta institutionnel n'est pas la couleur de référence pour un badge « neutre/info ».

### Cards / Containers
- **Corner Style:** 8px (écrans de gestion) ou 16px (tableau de bord).
- **Background:** blanc sur fond de page gris pierre.
- **Shadow Strategy:** aucune (voir Elevation & Depth).
- **Border:** `1px solid` gris pierre trait (`#e7e5e4`).
- **Internal Padding:** 16px (cartes de formulaire/liste), 20px (cartes de statistiques du tableau de bord).

### Inputs / Fields
- **Style:** bordure gris pierre bordure, fond blanc, `rounded-lg`, `text-sm`. Pas de classe de focus personnalisée sur la quasi-totalité du produit — l'anneau de focus vient du comportement par défaut du plugin `@tailwindcss/forms`.
- **Label:** au-dessus du champ, `text-sm font-medium` gris pierre corps, `mt-1` entre libellé et champ.
- **Error:** message `text-sm text-red-600` sous le champ.

### Navigation
- **Sidebar (écran interne) :** liens `text-sm font-medium`, gris pierre corps au repos, hover fond gris pierre fond-alt ; état actif = fond terracotta institutionnel teinte + texte terracotta institutionnel, coins `rounded-xl`. Groupes de section précédés d'une icône et d'un libellé `text-xs uppercase tracking-wide` gris pierre passif.
- **Icônes :** trait fin (`stroke-width: 1.6`), tracé inline SVG fait main (pas de bibliothèque d'icônes chargée), toujours 20×20 (liens) ou 16×16 (en-têtes de groupe).
- **Barre du portail parents :** navigation horizontale simple, pas d'icônes, liens texte `text-sm`.

## Do's and Don'ts

### Do:
- **Do** réserver le terracotta institutionnel à l'action et à l'état actif — jamais en fond de section ou de page.
- **Do** garder les écrans de gestion plats (bordure + fond blanc, aucune ombre) ; réserver l'ombre aux éléments flottants et aux écrans d'entrée.
- **Do** utiliser `rounded-lg` par défaut pour tout nouveau composant ; passer à `rounded-xl` pour un élément de navigation, `rounded-2xl` pour une carte de type tableau de bord/vue d'ensemble.
- **Do** garder toute l'interface en une seule police (Instrument Sans) — construire la hiérarchie par taille/graisse, jamais par changement de famille.

### Don't:
- **Don't** copier les classes `shadow-sm` / `focus:ring-stone-500` des écrans de connexion/inscription dans un nouvel écran — c'est un reste du gabarit par défaut de Laravel, pas la convention du reste du produit (voir Elevation & Depth).
- **Don't** traiter `resources/views/welcome.blade.php` comme une référence de design — c'est la page de démonstration par défaut de Laravel (encore sur l'ancienne palette indigo/slate), jamais liée dans la navigation réelle et non alignée avec ce système.
- **Don't** introduire une nouvelle couleur de statut sans vérifier `bg-emerald-*` (majoritaire) vs `bg-green-*` (minoritaire) pour « actif » — une incohérence connue, non encore arbitrée, à corriger plutôt qu'à étendre.
- **Don't** construire de modale personnalisée pour une confirmation destructive sans décision explicite — le produit s'appuie aujourd'hui uniquement sur `wire:confirm` natif.
- **Don't** réintroduire des classes `indigo-*` ou `slate-*` dans un nouvel écran — l'accent et les neutres sont désormais `orange-*` (terracotta) et `stone-*` (pierre) partout, `welcome.blade.php` excepté.
