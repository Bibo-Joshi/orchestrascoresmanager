# Copilot Instructions for `/src` (Vue3/TypeScript Frontend)

## Vue3 Composition API
- Use `<script setup lang="ts">` exclusively
- Leverage Composition API (`ref`, `computed`, `watch`, etc.)
- Type props with TypeScript interfaces: `defineProps<Props>()`
- Type emits: `defineEmits<{ eventName: [argType] }>()`

## Component Architecture

### Separation of Concerns
- **Presentation components**: Reusable, no business logic, props-driven
- **Container components**: Fetch data, manage state, compose presentation components
- **Page components**: Route-level, orchestrate containers

### DRY Principles
- Extract reusable logic into composables (`composables/*.ts`)
- Share types via `types/*.ts`
- Use `@nextcloud/vue` components instead of reimplementing (NcButton, NcModal, NcTextField, etc.)

## TypeScript Standards
- Explicit types for all function parameters and return values
- Interface definitions for props, emits, and complex data structures
- Avoid `any` - use `unknown` if type is truly unknown
- Use type imports: `import type { ... }`

## NextCloud Vue Components
Prefer these over custom implementations:
- `NcButton`, `NcActions`, `NcActionButton` for actions
- `NcModal`, `NcDialog` for dialogs
- `NcTextField`, `NcCheckbox`, `NcSelect` for forms
- `NcLoadingIcon`, `NcEmptyContent` for states
- `NcAppNavigation`, `NcAppContent` for layout

## State Management with Pinia Stores

### Store-Centric Data Pattern
**All API calls must be made through Pinia stores.** Components should never call the API directly. This ensures:
- Centralized state management
- Consistent optimistic updates with automatic rollback on errors
- Reactive updates propagate to all consuming components

### Store Structure
Each entity store (`/stores/*.ts`) should provide:
- `initialize()` - Load data from initial state or fallback to API
- `create*()` - Create via API, then add to local state
- `update*FieldApi()` - Update via API with optimistic local updates
- `delete*()` - Delete via API, then remove from local state

### Component Usage
```typescript
// ✅ CORRECT - Use store methods
const scoresStore = useScoresStore()
await scoresStore.createScore(title)
await scoresStore.updateScoreFieldApi(id, 'title', newTitle)

// ❌ WRONG - Direct API calls in components
await apiClients.default.scoreApiPostScore({ title })
scoresStore.addScore(created) // Manually updating store
```

### Example Store Pattern
```typescript
export const useExampleStore = defineStore('example', () => {
  const items = ref<Item[]>([])
  
  // Create with API + local state update
  async function createItem(data: CreateData): Promise<Item> {
    const response = await apiClients.default.itemApiPostItem(data)
    const created = response.data.ocs.data
    items.value.unshift(created)
    return created
  }
  
  // Update with optimistic local update + rollback on error
  async function updateItemFieldApi(id: number, field: string, value: unknown): Promise<void> {
    const index = items.value.findIndex(i => i.id === id)
    if (index === -1) return
    
    const oldValue = items.value[index][field]
    items.value[index] = { ...items.value[index], [field]: value }
    
    try {
      await apiClients.default.itemApiPatchItem(id, { [field]: value })
    } catch (error) {
      items.value[index] = { ...items.value[index], [field]: oldValue }
      throw error
    }
  }
})
```

## API Integration
- Use auto-generated API client from `/api/generated/`
- **All API calls go through stores** - never call API directly from components
- Handle loading/error states in components using store's `isLoading`/`isLoaded` refs

## Error Handling
- Use `tryShowError()` from `/utils/errorHandling` to wrap async operations with automatic error display
- Automatically extracts OCS error messages (`response.data.ocs.meta.message`) for user-friendly error display
- Example: `await tryShowError(async () => await store.deleteItem(id), t('Failed to delete: '), () => revertUIChanges())`

## Styling
- Use scoped styles: `<style scoped>`
- Follow NextCloud design tokens and CSS variables
- Avoid inline styles - use classes

## l10n (Localization)

### Import Statement
**ALWAYS** use the project's l10n wrapper instead of direct @nextcloud/l10n import:

```typescript
import { t } from '../../../utils/l10n'     // ✅ CORRECT - for singular
import { t, n } from '../../../utils/l10n'  // ✅ CORRECT - for singular + plural
import { t } from '@nextcloud/l10n'         // ❌ WRONG
```

The path may vary depending on your file location. Adjust the relative path to point to `/src/utils/l10n.ts`.

### Translation Functions
Use the `t()` function for singular translations and `n()` for plurals:

```typescript
// Simple string
const message = t('Hello, world!')

// String with placeholders
const greeting = t('Hello, {name}!', { name: userName })

// Plural forms
const itemCount = n('%n item', '%n items', count)
const filesMsg = n('Delete %n file?', 'Delete %n files?', count)
```

### Adding Translations
**WHENEVER** you add a new `t()` or `n()` call, add the translation to the `.po` file first:

1. **Edit the PO file** at `translationfiles/de/orchestrascoresmanager.po`:
   ```po
   msgid "Your English text"
   msgstr "Ihr deutscher Text"
   ```

2. **Generate the l10n files** by running:
   ```bash
   php translationtool.phar convert-po-files
   ```

This will automatically update `/l10n/de.json` and `/l10n/de.js`.

**Note**: The GitHub Actions workflow will verify that translation files are up to date on pull requests.

### Best Practices
- Keep strings short and clear
- Use placeholders for dynamic content
- Avoid concatenating translated strings
- Include context comments for ambiguous strings

## Icons

### Import Statement
**ALWAYS** use the project's icon alias modules instead of importing directly from `vue-material-design-icons` or `@mdi/svg`:

```typescript
// For Vue components (vue-material-design-icons)
import { ScoreIcon, DeleteIcon, ConfirmIcon } from '@/icons/vue-material'  // ✅ CORRECT
import ScoreIcon from 'vue-material-design-icons/FileMusic.vue'            // ❌ WRONG

// For raw SVG strings (@mdi/svg) - used with NcButton etc.
import { ConfirmIconRaw, CancelIconRaw } from '@/icons/mdi'                // ✅ CORRECT
import IconArrowRight from '@mdi/svg/svg/arrow-right.svg?raw'              // ❌ WRONG
```

### Available Icons

**Vue Material Icons** (`@/icons/vue-material`):
- **Entity icons**: `ScoreIcon`, `ScoreBookIcon`, `FolderCollectionIcon`
- **Action icons**: `AddIcon`, `DeleteIcon`, `ConfirmIcon`, `CancelIcon`, `DownloadIcon`, `OpenExternalIcon`
- **State icons**: `ErrorIcon`, `InfoIcon`
- **Comment icons**: `CommentIcon`
- **Navigation icons**: `ExpandedIcon`, `CollapsedIcon`

**MDI SVG Icons** (`@/icons/mdi`):
- **Action icons**: `ConfirmIconRaw`, `CancelIconRaw`

### Adding New Icons
When you need an icon that doesn't exist in the alias modules:
1. Add the alias to the appropriate module (`/src/icons/vue-material/index.ts` or `/src/icons/mdi/index.ts`)
2. Use a usage-based name (e.g., `EditIcon` not `PencilIcon`)
3. Import from the alias module in your component
