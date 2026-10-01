import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\SanctionController::destroy
* @see app/Http/Controllers/Admin/SanctionController.php:32
* @route '/admin/users/{ulid}/sanction'
*/
export const destroy = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/admin/users/{ulid}/sanction',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Admin\SanctionController::destroy
* @see app/Http/Controllers/Admin/SanctionController.php:32
* @route '/admin/users/{ulid}/sanction'
*/
destroy.url = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { ulid: args }
    }

    if (Array.isArray(args)) {
        args = {
            ulid: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        ulid: args.ulid,
    }

    return destroy.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\SanctionController::destroy
* @see app/Http/Controllers/Admin/SanctionController.php:32
* @route '/admin/users/{ulid}/sanction'
*/
destroy.delete = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const sanction = {
    destroy: Object.assign(destroy, destroy),
}

export default sanction