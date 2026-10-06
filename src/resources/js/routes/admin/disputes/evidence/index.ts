import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\DisputeController::destroy
* @see app/Http/Controllers/Admin/DisputeController.php:103
* @route '/admin/disputes/{ulid}/evidence/{media}'
*/
export const destroy = (args: { ulid: string | number, media: string | number } | [ulid: string | number, media: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/admin/disputes/{ulid}/evidence/{media}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Admin\DisputeController::destroy
* @see app/Http/Controllers/Admin/DisputeController.php:103
* @route '/admin/disputes/{ulid}/evidence/{media}'
*/
destroy.url = (args: { ulid: string | number, media: string | number } | [ulid: string | number, media: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            ulid: args[0],
            media: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        ulid: args.ulid,
        media: args.media,
    }

    return destroy.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace('{media}', parsedArgs.media.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\DisputeController::destroy
* @see app/Http/Controllers/Admin/DisputeController.php:103
* @route '/admin/disputes/{ulid}/evidence/{media}'
*/
destroy.delete = (args: { ulid: string | number, media: string | number } | [ulid: string | number, media: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const evidence = {
    destroy: Object.assign(destroy, destroy),
}

export default evidence