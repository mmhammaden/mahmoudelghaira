import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Button } from "./Button.vue"

export const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-[0.35rem] border border-transparent text-base font-normal leading-6 transition-colors disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:ring-[0.2rem] focus-visible:ring-[#4e73df40] aria-invalid:ring-destructive/20 aria-invalid:border-destructive",
  {
    variants: {
      variant: {
        default:
          "border-[#4e73df] bg-[#4e73df] text-white hover:border-[#2653d4] hover:bg-[#2e59d9]",
        destructive:
          "border-[#e74a3b] bg-[#e74a3b] text-white hover:border-[#d52a1a] hover:bg-[#d52a1a] focus-visible:ring-[#e74a3b40]",
        outline:
          "border-[#d1d3e2] bg-white text-[#6e707e] hover:border-[#c4c6cc] hover:bg-[#eaecf4] hover:text-[#3a3b45]",
        secondary:
          "bg-secondary text-secondary-foreground hover:bg-secondary/80",
        ghost:
          "hover:bg-accent hover:text-accent-foreground dark:hover:bg-accent/50",
        link: "text-primary underline-offset-4 hover:underline",
      },
      size: {
        "default": "min-h-[calc(1.5em+0.75rem+2px)] px-3 py-1.5 has-[>svg]:px-3",
        "sm": "min-h-[calc(1.5em+0.5rem+2px)] gap-1.5 rounded-[0.2rem] px-2 py-1 text-sm has-[>svg]:px-2",
        "lg": "min-h-[calc(1.5em+1rem+2px)] px-4 py-2 text-xl has-[>svg]:px-4",
        "icon": "size-[calc(1.5em+0.75rem+2px)] p-1.5",
        "icon-sm": "size-[calc(1.5em+0.5rem+2px)] p-1",
        "icon-lg": "size-[calc(1.5em+1rem+2px)] p-2",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
)
export type ButtonVariants = VariantProps<typeof buttonVariants>
