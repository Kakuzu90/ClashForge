import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\DisputeController::index
* @see app/Http/Controllers/Admin/DisputeController.php:35
* @route '/admin/disputes'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/disputes',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\DisputeController::index
* @see app/Http/Controllers/Admin/DisputeController.php:35
* @route '/admin/disputes'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\DisputeController::index
* @see app/Http/Controllers/Admin/DisputeController.php:35
* @route '/admin/disputes'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\DisputeController::index
* @see app/Http/Controllers/Admin/DisputeController.php:35
* @route '/admin/disputes'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\DisputeController::show
* @see app/Http/Controllers/Admin/DisputeController.php:57
* @route '/admin/disputes/{ulid}'
*/
export const show = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/admin/disputes/{ulid}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\DisputeController::show
* @see app/Http/Controllers/Admin/DisputeController.php:57
* @route '/admin/disputes/{ulid}'
*/
show.url = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\DisputeController::show
* @see app/Http/Controllers/Admin/DisputeController.php:57
* @route '/admin/disputes/{ulid}'
*/
show.get = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\DisputeController::show
* @see app/Http/Controllers/Admin/DisputeController.php:57
* @route '/admin/disputes/{ulid}'
*/
show.head = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\DisputeController::decide
* @see app/Http/Controllers/Admin/DisputeController.php:74
* @route '/admin/disputes/{ulid}/decision'
*/
export const decide = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: decide.url(args, options),
    method: 'post',
})

decide.definition = {
    methods: ["post"],
    url: '/admin/disputes/{ulid}/decision',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\DisputeController::decide
* @see app/Http/Controllers/Admin/DisputeController.php:74
* @route '/admin/disputes/{ulid}/decision'
*/
decide.url = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return decide.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\DisputeController::decide
* @see app/Http/Controllers/Admin/DisputeController.php:74
* @route '/admin/disputes/{ulid}/decision'
*/
decide.post = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: decide.url(args, options),
    method: 'post',
})

const DisputeController = { index, show, decide }

export default DisputeController