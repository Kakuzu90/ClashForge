import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Account\SuspendedController::__invoke
* @see app/Http/Controllers/Account/SuspendedController.php:17
* @route '/account/suspended'
*/
export const suspended = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: suspended.url(options),
    method: 'get',
})

suspended.definition = {
    methods: ["get","head"],
    url: '/account/suspended',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Account\SuspendedController::__invoke
* @see app/Http/Controllers/Account/SuspendedController.php:17
* @route '/account/suspended'
*/
suspended.url = (options?: RouteQueryOptions) => {
    return suspended.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Account\SuspendedController::__invoke
* @see app/Http/Controllers/Account/SuspendedController.php:17
* @route '/account/suspended'
*/
suspended.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: suspended.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Account\SuspendedController::__invoke
* @see app/Http/Controllers/Account/SuspendedController.php:17
* @route '/account/suspended'
*/
suspended.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: suspended.url(options),
    method: 'head',
})

const account = {
    suspended: Object.assign(suspended, suspended),
}

export default account