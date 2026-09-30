import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Account\SuspendedController::__invoke
* @see app/Http/Controllers/Account/SuspendedController.php:17
* @route '/account/suspended'
*/
const SuspendedController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: SuspendedController.url(options),
    method: 'get',
})

SuspendedController.definition = {
    methods: ["get","head"],
    url: '/account/suspended',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Account\SuspendedController::__invoke
* @see app/Http/Controllers/Account/SuspendedController.php:17
* @route '/account/suspended'
*/
SuspendedController.url = (options?: RouteQueryOptions) => {
    return SuspendedController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Account\SuspendedController::__invoke
* @see app/Http/Controllers/Account/SuspendedController.php:17
* @route '/account/suspended'
*/
SuspendedController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: SuspendedController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Account\SuspendedController::__invoke
* @see app/Http/Controllers/Account/SuspendedController.php:17
* @route '/account/suspended'
*/
SuspendedController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: SuspendedController.url(options),
    method: 'head',
})

export default SuspendedController