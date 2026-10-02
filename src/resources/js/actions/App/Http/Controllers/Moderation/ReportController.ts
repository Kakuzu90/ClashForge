import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Moderation\ReportController::index
* @see app/Http/Controllers/Moderation/ReportController.php:17
* @route '/moderation/reports'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/moderation/reports',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Moderation\ReportController::index
* @see app/Http/Controllers/Moderation/ReportController.php:17
* @route '/moderation/reports'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Moderation\ReportController::index
* @see app/Http/Controllers/Moderation/ReportController.php:17
* @route '/moderation/reports'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Moderation\ReportController::index
* @see app/Http/Controllers/Moderation/ReportController.php:17
* @route '/moderation/reports'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

const ReportController = { index }

export default ReportController