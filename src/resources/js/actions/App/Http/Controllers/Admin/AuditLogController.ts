import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\AuditLogController::__invoke
* @see app/Http/Controllers/Admin/AuditLogController.php:24
* @route '/admin/audit'
*/
const AuditLogController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AuditLogController.url(options),
    method: 'get',
})

AuditLogController.definition = {
    methods: ["get","head"],
    url: '/admin/audit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\AuditLogController::__invoke
* @see app/Http/Controllers/Admin/AuditLogController.php:24
* @route '/admin/audit'
*/
AuditLogController.url = (options?: RouteQueryOptions) => {
    return AuditLogController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AuditLogController::__invoke
* @see app/Http/Controllers/Admin/AuditLogController.php:24
* @route '/admin/audit'
*/
AuditLogController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AuditLogController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\AuditLogController::__invoke
* @see app/Http/Controllers/Admin/AuditLogController.php:24
* @route '/admin/audit'
*/
AuditLogController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AuditLogController.url(options),
    method: 'head',
})

export default AuditLogController