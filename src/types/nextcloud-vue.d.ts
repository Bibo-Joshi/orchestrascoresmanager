// Type declarations for Nextcloud Vue components
declare module '@nextcloud/vue/dist/Components/NcContent.js' {
  import type { DefineComponent } from 'vue'
  const NcContent: DefineComponent
  export default NcContent
}

declare module '@nextcloud/vue/dist/Components/NcAppNavigation.js' {
  import type { DefineComponent } from 'vue'
  const NcAppNavigation: DefineComponent
  export default NcAppNavigation
}

declare module '@nextcloud/vue/dist/Components/NcAppNavigationNew.js' {
  import type { DefineComponent } from 'vue'
  const NcAppNavigationNew: DefineComponent
  export default NcAppNavigationNew
}

declare module '@nextcloud/vue/dist/Components/NcAppNavigationItem.js' {
  import type { DefineComponent } from 'vue'
  const NcAppNavigationItem: DefineComponent
  export default NcAppNavigationItem
}

declare module '@nextcloud/vue/dist/Components/NcAppContent.js' {
  import type { DefineComponent } from 'vue'
  const NcAppContent: DefineComponent
  export default NcAppContent
}

declare module '@nextcloud/vue/dist/Components/NcAppSidebar.js' {
  import type { DefineComponent } from 'vue'
  const NcAppSidebar: DefineComponent
  export default NcAppSidebar
}

declare module '@nextcloud/vue/dist/Components/NcButton.js' {
  import type { DefineComponent } from 'vue'
  const NcButton: DefineComponent
  export default NcButton
}

declare module '@nextcloud/vue/components/NcDialog' {
  import type { DefineComponent } from 'vue'

  export interface NcDialogButtonProps {
    label: string
    callback?: () => unknown | Promise<unknown>
    disabled?: boolean
    icon?: string
    type?: 'primary' | 'button' | 'link' | string
    variant?: string
    nativeType?: 'submit' | 'reset' | 'button' | string
  }

  const NcDialog: DefineComponent
  export default NcDialog
}

declare module '@nextcloud/vue/components/NcTextField' {
  import type { DefineComponent } from 'vue'
  const NcTextField: DefineComponent
  export default NcTextField
}
