import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Accounts\AccountImageController::store
* @see app/Http/Controllers/Accounts/AccountImageController.php:18
* @route '/accounts/{ulid}/images'
*/
export const store = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/accounts/{ulid}/images',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Accounts\AccountImageController::store
* @see app/Http/Controllers/Accounts/AccountImageController.php:18
* @route '/accounts/{ulid}/images'
*/
store.url = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return store.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Accounts\AccountImageController::store
* @see app/Http/Controllers/Accounts/AccountImageController.php:18
* @route '/accounts/{ulid}/images'
*/
store.post = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Accounts\AccountImageController::destroy
* @see app/Http/Controllers/Accounts/AccountImageController.php:25
* @route '/accounts/{ulid}/images/{media}'
*/
export const destroy = (args: { ulid: string | number, media: string | number } | [ulid: string | number, media: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/accounts/{ulid}/images/{media}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Accounts\AccountImageController::destroy
* @see app/Http/Controllers/Accounts/AccountImageController.php:25
* @route '/accounts/{ulid}/images/{media}'
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
* @see \App\Http\Controllers\Accounts\AccountImageController::destroy
* @see app/Http/Controllers/Accounts/AccountImageController.php:25
* @route '/accounts/{ulid}/images/{media}'
*/
destroy.delete = (args: { ulid: string | number, media: string | number } | [ulid: string | number, media: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const images = {
    store: Object.assign(store, store),
    destroy: Object.assign(destroy, destroy),
}

export default images