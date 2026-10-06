import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Accounts\AccountController::show
* @see app/Http/Controllers/Accounts/AccountController.php:24
* @route '/accounts/{ulid}'
*/
export const show = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/accounts/{ulid}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Accounts\AccountController::show
* @see app/Http/Controllers/Accounts/AccountController.php:24
* @route '/accounts/{ulid}'
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
* @see \App\Http\Controllers\Accounts\AccountController::show
* @see app/Http/Controllers/Accounts/AccountController.php:24
* @route '/accounts/{ulid}'
*/
show.get = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Accounts\AccountController::show
* @see app/Http/Controllers/Accounts/AccountController.php:24
* @route '/accounts/{ulid}'
*/
show.head = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

const AccountController = { show }

export default AccountController