import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Bases\BaseFeedController::__invoke
* @see app/Http/Controllers/Bases/BaseFeedController.php:17
* @route '/bases'
*/
const BaseFeedController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: BaseFeedController.url(options),
    method: 'get',
})

BaseFeedController.definition = {
    methods: ["get","head"],
    url: '/bases',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Bases\BaseFeedController::__invoke
* @see app/Http/Controllers/Bases/BaseFeedController.php:17
* @route '/bases'
*/
BaseFeedController.url = (options?: RouteQueryOptions) => {
    return BaseFeedController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Bases\BaseFeedController::__invoke
* @see app/Http/Controllers/Bases/BaseFeedController.php:17
* @route '/bases'
*/
BaseFeedController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: BaseFeedController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Bases\BaseFeedController::__invoke
* @see app/Http/Controllers/Bases/BaseFeedController.php:17
* @route '/bases'
*/
BaseFeedController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: BaseFeedController.url(options),
    method: 'head',
})

export default BaseFeedController