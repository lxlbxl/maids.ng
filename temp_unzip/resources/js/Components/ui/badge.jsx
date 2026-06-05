import * as React from "react"
import { cva } from "class-variance-authority"
import { cn } from "@/lib/utils"

const badgeVariants = cva(
    "inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-teal focus:ring-offset-2",
    {
        variants: {
            variant: {
                default:
                    "border-transparent bg-teal text-white hover:bg-teal-dark",
                secondary:
                    "border-transparent bg-ivory text-espresso hover:bg-gray-100",
                destructive:
                    "border-transparent bg-danger text-white hover:bg-danger/80",
                outline: "text-espresso border-gray-200",
            },
        },
        defaultVariants: {
            variant: "default",
        },
    }
)

function Badge({
    className,
    variant,
    ...props
}) {
    return (
        <div className={cn(badgeVariants({ variant }), className)} {...props} />
    )
}

export { Badge, badgeVariants }
