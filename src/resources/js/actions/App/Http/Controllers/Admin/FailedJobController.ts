import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\FailedJobController::retry
* @see app/Http/Controllers/Admin/FailedJobController.php:19
* @route '/admin/system/failed-jobs/retry'
*/
export const retry = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: retry.url(options),
    method: 'post',
})

retry.definition = {
    methods: ["post"],
    url: '/admin/system/failed-jobs/retry',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\FailedJobController::retry
* @see app/Http/Controllers/Admin/FailedJobController.php:19
* @route '/admin/system/failed-jobs/retry'
*/
retry.url = (options?: RouteQueryOptions) => {
    return retry.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\FailedJobController::retry
* @see app/Http/Controllers/Admin/FailedJobController.php:19
* @route '/admin/system/failed-jobs/retry'
*/
retry.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: retry.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\FailedJobController::destroy
* @see app/Http/Controllers/Admin/FailedJobController.php:26
* @route '/admin/system/failed-jobs'
*/
export const destroy = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/admin/system/failed-jobs',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Admin\FailedJobController::destroy
* @see app/Http/Controllers/Admin/FailedJobController.php:26
* @route '/admin/system/failed-jobs'
*/
destroy.url = (options?: RouteQueryOptions) => {
    return destroy.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\FailedJobController::destroy
* @see app/Http/Controllers/Admin/FailedJobController.php:26
* @route '/admin/system/failed-jobs'
*/
destroy.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

const FailedJobController = { retry, destroy }

export default FailedJobController