import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Notifications\UnsubscribeController::result
* @see app/Http/Controllers/Notifications/UnsubscribeController.php:30
* @route '/notifications/unsubscribe/done'
*/
export const result = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: result.url(options),
    method: 'get',
})

result.definition = {
    methods: ["get","head"],
    url: '/notifications/unsubscribe/done',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Notifications\UnsubscribeController::result
* @see app/Http/Controllers/Notifications/UnsubscribeController.php:30
* @route '/notifications/unsubscribe/done'
*/
result.url = (options?: RouteQueryOptions) => {
    return result.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Notifications\UnsubscribeController::result
* @see app/Http/Controllers/Notifications/UnsubscribeController.php:30
* @route '/notifications/unsubscribe/done'
*/
result.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: result.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Notifications\UnsubscribeController::result
* @see app/Http/Controllers/Notifications/UnsubscribeController.php:30
* @route '/notifications/unsubscribe/done'
*/
result.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: result.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Notifications\UnsubscribeController::show
* @see app/Http/Controllers/Notifications/UnsubscribeController.php:16
* @route '/notifications/unsubscribe/{ulid}/{hash}'
*/
export const show = (args: { ulid: string | number, hash: string | number } | [ulid: string | number, hash: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/notifications/unsubscribe/{ulid}/{hash}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Notifications\UnsubscribeController::show
* @see app/Http/Controllers/Notifications/UnsubscribeController.php:16
* @route '/notifications/unsubscribe/{ulid}/{hash}'
*/
show.url = (args: { ulid: string | number, hash: string | number } | [ulid: string | number, hash: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            ulid: args[0],
            hash: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        ulid: args.ulid,
        hash: args.hash,
    }

    return show.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace('{hash}', parsedArgs.hash.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Notifications\UnsubscribeController::show
* @see app/Http/Controllers/Notifications/UnsubscribeController.php:16
* @route '/notifications/unsubscribe/{ulid}/{hash}'
*/
show.get = (args: { ulid: string | number, hash: string | number } | [ulid: string | number, hash: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Notifications\UnsubscribeController::show
* @see app/Http/Controllers/Notifications/UnsubscribeController.php:16
* @route '/notifications/unsubscribe/{ulid}/{hash}'
*/
show.head = (args: { ulid: string | number, hash: string | number } | [ulid: string | number, hash: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Notifications\UnsubscribeController::store
* @see app/Http/Controllers/Notifications/UnsubscribeController.php:23
* @route '/notifications/unsubscribe/{ulid}/{hash}'
*/
export const store = (args: { ulid: string | number, hash: string | number } | [ulid: string | number, hash: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/notifications/unsubscribe/{ulid}/{hash}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Notifications\UnsubscribeController::store
* @see app/Http/Controllers/Notifications/UnsubscribeController.php:23
* @route '/notifications/unsubscribe/{ulid}/{hash}'
*/
store.url = (args: { ulid: string | number, hash: string | number } | [ulid: string | number, hash: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            ulid: args[0],
            hash: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        ulid: args.ulid,
        hash: args.hash,
    }

    return store.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace('{hash}', parsedArgs.hash.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Notifications\UnsubscribeController::store
* @see app/Http/Controllers/Notifications/UnsubscribeController.php:23
* @route '/notifications/unsubscribe/{ulid}/{hash}'
*/
store.post = (args: { ulid: string | number, hash: string | number } | [ulid: string | number, hash: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

const UnsubscribeController = { result, show, store }

export default UnsubscribeController