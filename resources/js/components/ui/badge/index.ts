import { cva, type VariantProps } from 'class-variance-authority';

export { default as Badge } from './Badge.vue';

export const badgeVariants = cva('inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap', {
    variants: {
        variant: {
            default: 'bg-primary text-primary-foreground',
            muted: 'bg-muted text-muted-foreground',
            brand: 'bg-brand-soft text-brand',
            success: 'bg-success-soft text-success',
            outline: 'border text-muted-foreground',
        },
    },
    defaultVariants: {
        variant: 'default',
    },
});

export type BadgeVariants = VariantProps<typeof badgeVariants>;
