import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Bases\BaseFeedController::__invoke
* @see app/Http/Controllers/Bases/BaseFeedController.php:17
* @route '/bases'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/bases',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Bases\BaseFeedController::__invoke
* @see app/Http/Controllers/Bases/BaseFeedController.php:17
* @route '/bases'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Bases\BaseFeedController::__invoke
* @see app/Http/Controllers/Bases/BaseFeedController.php:17
* @route '/bases'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Bases\BaseFeedController::__invoke
* @see app/Http/Controllers/Bases/BaseFeedController.php:17
* @route '/bases'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

const bases = {
    index: Object.assign(index, index),
}

export default bases