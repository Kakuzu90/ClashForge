import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Web\HealthController::__invoke
* @see app/Http/Controllers/Web/HealthController.php:16
* @route '/health'
*/
const HealthController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: HealthController.url(options),
    method: 'get',
})

HealthController.definition = {
    methods: ["get","head"],
    url: '/health',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Web\HealthController::__invoke
* @see app/Http/Controllers/Web/HealthController.php:16
* @route '/health'
*/
HealthController.url = (options?: RouteQueryOptions) => {
    return HealthController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Web\HealthController::__invoke
* @see app/Http/Controllers/Web/HealthController.php:16
* @route '/health'
*/
HealthController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: HealthController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Web\HealthController::__invoke
* @see app/Http/Controllers/Web/HealthController.php:16
* @route '/health'
*/
HealthController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: HealthController.url(options),
    method: 'head',
})

export default HealthController