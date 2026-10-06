import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Disputes\DisputeController::create
* @see app/Http/Controllers/Disputes/DisputeController.php:33
* @route '/disputes/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/disputes/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Disputes\DisputeController::create
* @see app/Http/Controllers/Disputes/DisputeController.php:33
* @route '/disputes/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Disputes\DisputeController::create
* @see app/Http/Controllers/Disputes/DisputeController.php:33
* @route '/disputes/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Disputes\DisputeController::create
* @see app/Http/Controllers/Disputes/DisputeController.php:33
* @route '/disputes/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Disputes\DisputeController::show
* @see app/Http/Controllers/Disputes/DisputeController.php:72
* @route '/disputes/{ulid}'
*/
export const show = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/disputes/{ulid}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Disputes\DisputeController::show
* @see app/Http/Controllers/Disputes/DisputeController.php:72
* @route '/disputes/{ulid}'
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
* @see \App\Http\Controllers\Disputes\DisputeController::show
* @see app/Http/Controllers/Disputes/DisputeController.php:72
* @route '/disputes/{ulid}'
*/
show.get = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Disputes\DisputeController::show
* @see app/Http/Controllers/Disputes/DisputeController.php:72
* @route '/disputes/{ulid}'
*/
show.head = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Disputes\DisputeController::store
* @see app/Http/Controllers/Disputes/DisputeController.php:60
* @route '/disputes'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/disputes',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Disputes\DisputeController::store
* @see app/Http/Controllers/Disputes/DisputeController.php:60
* @route '/disputes'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Disputes\DisputeController::store
* @see app/Http/Controllers/Disputes/DisputeController.php:60
* @route '/disputes'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Disputes\DisputeController::respond
* @see app/Http/Controllers/Disputes/DisputeController.php:85
* @route '/disputes/{ulid}/respond'
*/
export const respond = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: respond.url(args, options),
    method: 'post',
})

respond.definition = {
    methods: ["post"],
    url: '/disputes/{ulid}/respond',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Disputes\DisputeController::respond
* @see app/Http/Controllers/Disputes/DisputeController.php:85
* @route '/disputes/{ulid}/respond'
*/
respond.url = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return respond.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Disputes\DisputeController::respond
* @see app/Http/Controllers/Disputes/DisputeController.php:85
* @route '/disputes/{ulid}/respond'
*/
respond.post = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: respond.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Disputes\DisputeController::withdraw
* @see app/Http/Controllers/Disputes/DisputeController.php:96
* @route '/disputes/{ulid}/withdraw'
*/
export const withdraw = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: withdraw.url(args, options),
    method: 'post',
})

withdraw.definition = {
    methods: ["post"],
    url: '/disputes/{ulid}/withdraw',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Disputes\DisputeController::withdraw
* @see app/Http/Controllers/Disputes/DisputeController.php:96
* @route '/disputes/{ulid}/withdraw'
*/
withdraw.url = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return withdraw.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Disputes\DisputeController::withdraw
* @see app/Http/Controllers/Disputes/DisputeController.php:96
* @route '/disputes/{ulid}/withdraw'
*/
withdraw.post = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: withdraw.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Disputes\DisputeController::release
* @see app/Http/Controllers/Disputes/DisputeController.php:107
* @route '/disputes/{ulid}/release'
*/
export const release = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: release.url(args, options),
    method: 'post',
})

release.definition = {
    methods: ["post"],
    url: '/disputes/{ulid}/release',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Disputes\DisputeController::release
* @see app/Http/Controllers/Disputes/DisputeController.php:107
* @route '/disputes/{ulid}/release'
*/
release.url = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return release.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Disputes\DisputeController::release
* @see app/Http/Controllers/Disputes/DisputeController.php:107
* @route '/disputes/{ulid}/release'
*/
release.post = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: release.url(args, options),
    method: 'post',
})

const DisputeController = { create, show, store, respond, withdraw, release }

export default DisputeController