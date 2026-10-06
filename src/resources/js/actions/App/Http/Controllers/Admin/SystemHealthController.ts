import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\SystemHealthController::__invoke
* @see app/Http/Controllers/Admin/SystemHealthController.php:28
* @route '/admin/system'
*/
const SystemHealthController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: SystemHealthController.url(options),
    method: 'get',
})

SystemHealthController.definition = {
    methods: ["get","head"],
    url: '/admin/system',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\SystemHealthController::__invoke
* @see app/Http/Controllers/Admin/SystemHealthController.php:28
* @route '/admin/system'
*/
SystemHealthController.url = (options?: RouteQueryOptions) => {
    return SystemHealthController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\SystemHealthController::__invoke
* @see app/Http/Controllers/Admin/SystemHealthController.php:28
* @route '/admin/system'
*/
SystemHealthController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: SystemHealthController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\SystemHealthController::__invoke
* @see app/Http/Controllers/Admin/SystemHealthController.php:28
* @route '/admin/system'
*/
SystemHealthController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: SystemHealthController.url(options),
    method: 'head',
})

export default SystemHealthController