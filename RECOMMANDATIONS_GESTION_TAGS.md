# 🏷️ Recommandations - Gestion des Tags

**Date** : 2025-01-27  
**Priorité** : 🔴 **HAUTE**  
**Statut** : ❌ **NON IMPLÉMENTÉ**

---

## 📋 Problème Identifié

La gestion des tags est **complètement absente** de l'interface utilisateur. Les tags existent dans le modèle et le service, mais il n'y a :

- ❌ Aucune page pour créer/modifier/supprimer des tags
- ❌ Aucun filtre par tags dans les listes de leads
- ❌ Aucun lien dans la sidebar pour accéder à la gestion des tags
- ❌ Aucune création rapide de tags depuis les pages de détails

---

## 🎯 Solution Proposée

### Phase 1 : Pages de Gestion des Tags

#### 1.1. Pour Super Admin

**Routes à créer :**
```php
// Dans routes/web.php, section Super Admin
Volt::route('admin/tags', 'admin.tags')
    ->name('admin.tags');
Volt::route('admin/tags/create', 'admin.tags.create')
    ->name('admin.tags.create');
Volt::route('admin/tags/{tag}/edit', 'admin.tags.edit')
    ->name('admin.tags.edit');
```

**Composants à créer :**
- `resources/views/livewire/admin/tags.blade.php` - Liste des tags
- `resources/views/livewire/admin/tags/create.blade.php` - Création
- `resources/views/livewire/admin/tags/edit.blade.php` - Édition

**Fonctionnalités :**
- Liste paginée de tous les tags
- Création de nouveaux tags (nom, couleur, description, catégorie)
- Édition des tags existants
- Suppression des tags (sauf système)
- Filtrage par catégorie
- Recherche par nom

#### 1.2. Pour Call Center Owner

**Routes à créer :**
```php
// Dans routes/web.php, section Owner
Volt::route('owner/tags', 'owner.tags')
    ->name('owner.tags');
Volt::route('owner/tags/create', 'owner.tags.create')
    ->name('owner.tags.create');
Volt::route('owner/tags/{tag}/edit', 'owner.tags.edit')
    ->name('owner.tags.edit');
```

**Composants à créer :**
- `resources/views/livewire/owner/tags.blade.php` - Liste des tags
- `resources/views/livewire/owner/tags/create.blade.php` - Création
- `resources/views/livewire/owner/tags/edit.blade.php` - Édition

**Fonctionnalités :**
- Liste des tags utilisés dans leur centre d'appels
- Création de tags spécifiques au centre
- Édition des tags (sauf système)
- Suppression des tags (sauf système)

---

### Phase 2 : Filtrage par Tags

#### 2.1. Ajouter le Filtre dans les Listes de Leads

**Pages à modifier :**
- `resources/views/livewire/admin/leads.blade.php`
- `resources/views/livewire/owner/leads.blade.php`
- `resources/views/livewire/supervisor/leads.blade.php`
- `resources/views/livewire/agent/leads.blade.php`

**Fonctionnalités à ajouter :**
- Multi-sélection de tags pour filtrer
- Combinaison avec les autres filtres (statut, date, etc.)
- Affichage des tags actifs dans les filtres
- Compteur de résultats par tag

**Exemple de code :**
```php
// Dans le composant
public array $selectedTags = [];

public function updatingSelectedTags(): void
{
    $this->resetPage();
}

// Dans la requête
->when($this->selectedTags, function ($query) {
    $query->whereHas('tags', function ($q) {
        $q->whereIn('tags.id', $this->selectedTags);
    });
})
```

---

### Phase 3 : Création Rapide de Tags

#### 3.1. Modal de Création dans les Pages de Détails

**Pages à modifier :**
- `resources/views/livewire/agent/leads/show.blade.php`
- `resources/views/livewire/admin/leads/show.blade.php`
- `resources/views/livewire/owner/leads/show.blade.php` (si existe)
- `resources/views/livewire/supervisor/leads/show.blade.php` (si existe)

**Fonctionnalités :**
- Bouton "Créer un nouveau tag" dans le modal d'ajout de tag
- Formulaire rapide (nom, couleur)
- Création et attachement automatique au lead
- Validation (nom unique)

---

### Phase 4 : Liens dans la Sidebar

#### 4.1. Ajouter les Liens

**Fichier à modifier :**
- `resources/views/components/layouts/app/sidebar.blade.php`

**Ajouts :**

```blade
@if (auth()->user()?->role?->slug === 'super_admin')
    <flux:navlist.group :heading="__('Gestion')" class="grid">
        <!-- ... autres liens ... -->
        <flux:navlist.item icon="tag" :href="route('admin.tags')" :current="request()->routeIs('admin.tags*')" wire:navigate>{{ __('Tags') }}</flux:navlist.item>
    </flux:navlist.group>
@endif

@if (auth()->user()?->role?->slug === 'call_center_owner')
    <flux:navlist.group :heading="__('Gestion')" class="grid">
        <!-- ... autres liens ... -->
        <flux:navlist.item icon="tag" :href="route('owner.tags')" :current="request()->routeIs('owner.tags*')" wire:navigate>{{ __('Tags') }}</flux:navlist.item>
    </flux:navlist.group>
@endif
```

---

## 📝 Structure des Fichiers à Créer

```
resources/views/livewire/
├── admin/
│   └── tags/
│       ├── create.blade.php
│       ├── edit.blade.php
│       └── (index.blade.php sera dans admin/tags.blade.php)
└── owner/
    └── tags/
        ├── create.blade.php
        ├── edit.blade.php
        └── (index.blade.php sera dans owner/tags.blade.php)
```

---

## 🔧 Modifications Nécessaires dans le Service

Le `TagService` existe déjà et a les méthodes nécessaires. Il faudra peut-être ajouter :

```php
// Dans app/Services/TagService.php

/**
 * Get all tags with usage count.
 */
public function getAllTagsWithCount(?CallCenter $callCenter = null): Collection
{
    $query = Tag::withCount('leads')
        ->orderBy('name');
    
    if ($callCenter) {
        $query->whereHas('leads', function ($q) use ($callCenter) {
            $q->where('call_center_id', $callCenter->id);
        });
    }
    
    return $query->get();
}

/**
 * Update a tag.
 */
public function updateTag(Tag $tag, array $data): Tag
{
    $tag->update($data);
    return $tag->fresh();
}
```

---

## ✅ Checklist d'Implémentation

### Super Admin
- [ ] Créer la route `admin.tags`
- [ ] Créer la route `admin.tags.create`
- [ ] Créer la route `admin.tags.edit`
- [ ] Créer le composant `admin.tags`
- [ ] Créer le composant `admin.tags.create`
- [ ] Créer le composant `admin.tags.edit`
- [ ] Ajouter le lien dans la sidebar
- [ ] Ajouter le filtre par tags dans `admin.leads`

### Call Center Owner
- [ ] Créer la route `owner.tags`
- [ ] Créer la route `owner.tags.create`
- [ ] Créer la route `owner.tags.edit`
- [ ] Créer le composant `owner.tags`
- [ ] Créer le composant `owner.tags.create`
- [ ] Créer le composant `owner.tags.edit`
- [ ] Ajouter le lien dans la sidebar
- [ ] Ajouter le filtre par tags dans `owner.leads`

### Filtrage
- [ ] Ajouter le filtre dans `admin.leads`
- [ ] Ajouter le filtre dans `owner.leads`
- [ ] Ajouter le filtre dans `supervisor.leads`
- [ ] Ajouter le filtre dans `agent.leads`

### Création Rapide
- [ ] Ajouter la création rapide dans `agent.leads.show`
- [ ] Ajouter la création rapide dans `admin.leads.show`
- [ ] Ajouter la création rapide dans les autres pages de détails

---

## 🎨 Exemple de Design

### Page de Liste des Tags

```blade
<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">{{ __('Tags') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Gérez les tags pour organiser vos leads') }}
            </p>
        </div>
        <flux:button href="{{ route('admin.tags.create') }}" variant="primary" icon="plus" wire:navigate>
            {{ __('Nouveau tag') }}
        </flux:button>
    </div>

    <!-- Liste des tags avec badges colorés -->
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @foreach($tags as $tag)
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="h-4 w-4 rounded-full" style="background-color: {{ $tag->color }};"></div>
                        <span class="font-semibold">{{ $tag->name }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-neutral-500">{{ $tag->leads_count }} leads</span>
                        <flux:button href="{{ route('admin.tags.edit', $tag) }}" variant="ghost" size="sm" wire:navigate>
                            {{ __('Modifier') }}
                        </flux:button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
```

---

## 📊 Estimation

| Tâche | Complexité | Temps estimé |
|-------|-----------|--------------|
| Pages de gestion (Admin) | Moyenne | 4 heures |
| Pages de gestion (Owner) | Moyenne | 3 heures |
| Filtrage par tags | Moyenne | 3 heures |
| Création rapide | Faible | 2 heures |
| Tests | Moyenne | 2 heures |
| **Total** | | **14 heures** |

---

**Conclusion** : La gestion des tags est un élément essentiel manquant qui doit être implémenté en priorité pour compléter le Sprint 8.



