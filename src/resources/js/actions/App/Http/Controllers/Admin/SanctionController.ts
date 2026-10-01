import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\SanctionController::suspend
* @see app/Http/Controllers/Admin/SanctionController.php:22
* @route '/admin/users/{ulid}/suspension'
*/
export const suspend = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: suspend.url(args, options),
    method: 'post',
})

suspend.definition = {
    methods: ["post"],
    url: '/admin/users/{ulid}/suspension',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\SanctionController::suspend
* @see app/Http/Controllers/Admin/SanctionController.php:22
* @route '/admin/users/{ulid}/suspension'
*/
suspend.url = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return suspend.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\SanctionController::suspend
* @see app/Http/Controllers/Admin/SanctionController.php:22
* @route '/admin/users/{ulid}/suspension'
*/
suspend.post = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: suspend.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\SanctionController::ban
* @see app/Http/Controllers/Admin/SanctionController.php:27
* @route '/admin/users/{ulid}/ban'
*/
export const ban = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ban.url(args, options),
    method: 'post',
})

ban.definition = {
    methods: ["post"],
    url: '/admin/users/{ulid}/ban',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\SanctionController::ban
* @see app/Http/Controllers/Admin/SanctionController.php:27
* @route '/admin/users/{ulid}/ban'
*/
ban.url = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return ban.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\SanctionController::ban
* @see app/Http/Controllers/Admin/SanctionController.php:27
* @route '/admin/users/{ulid}/ban'
*/
ban.post = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ban.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\SanctionController::lift
* @see app/Http/Controllers/Admin/SanctionController.php:32
* @route '/admin/users/{ulid}/sanction'
*/
export const lift = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: lift.url(args, options),
    method: 'delete',
})

lift.definition = {
    methods: ["delete"],
    url: '/admin/users/{ulid}/sanction',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Admin\SanctionController::lift
* @see app/Http/Controllers/Admin/SanctionController.php:32
* @route '/admin/users/{ulid}/sanction'
*/
lift.url = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return lift.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\SanctionController::lift
* @see app/Http/Controllers/Admin/SanctionController.php:32
* @route '/admin/users/{ulid}/sanction'
*/
lift.delete = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: lift.url(args, options),
    method: 'delete',
})

const SanctionController = { suspend, ban, lift }

export default SanctionController