import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\SecurityController::destroyOthers
* @see app/Http/Controllers/Settings/SecurityController.php:66
* @route '/settings/security/sessions'
*/
export const destroyOthers = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyOthers.url(options),
    method: 'delete',
})

destroyOthers.definition = {
    methods: ["delete"],
    url: '/settings/security/sessions',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Settings\SecurityController::destroyOthers
* @see app/Http/Controllers/Settings/SecurityController.php:66
* @route '/settings/security/sessions'
*/
destroyOthers.url = (options?: RouteQueryOptions) => {
    return destroyOthers.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\SecurityController::destroyOthers
* @see app/Http/Controllers/Settings/SecurityController.php:66
* @route '/settings/security/sessions'
*/
destroyOthers.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyOthers.url(options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Settings\SecurityController::destroy
* @see app/Http/Controllers/Settings/SecurityController.php:59
* @route '/settings/security/sessions/{key}'
*/
export const destroy = (args: { key: string | number } | [key: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/settings/security/sessions/{key}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Settings\SecurityController::destroy
* @see app/Http/Controllers/Settings/SecurityController.php:59
* @route '/settings/security/sessions/{key}'
*/
destroy.url = (args: { key: string | number } | [key: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { key: args }
    }

    if (Array.isArray(args)) {
        args = {
            key: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        key: args.key,
    }

    return destroy.definition.url
            .replace('{key}', parsedArgs.key.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\SecurityController::destroy
* @see app/Http/Controllers/Settings/SecurityController.php:59
* @route '/settings/security/sessions/{key}'
*/
destroy.delete = (args: { key: string | number } | [key: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const sessions = {
    destroyOthers: Object.assign(destroyOthers, destroyOthers),
    destroy: Object.assign(destroy, destroy),
}

export default sessions