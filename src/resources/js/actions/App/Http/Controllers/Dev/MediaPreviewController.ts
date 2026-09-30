import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Dev\MediaPreviewController::__invoke
* @see app/Http/Controllers/Dev/MediaPreviewController.php:25
* @route '/dev/media'
*/
const MediaPreviewController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: MediaPreviewController.url(options),
    method: 'get',
})

MediaPreviewController.definition = {
    methods: ["get","head"],
    url: '/dev/media',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Dev\MediaPreviewController::__invoke
* @see app/Http/Controllers/Dev/MediaPreviewController.php:25
* @route '/dev/media'
*/
MediaPreviewController.url = (options?: RouteQueryOptions) => {
    return MediaPreviewController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dev\MediaPreviewController::__invoke
* @see app/Http/Controllers/Dev/MediaPreviewController.php:25
* @route '/dev/media'
*/
MediaPreviewController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: MediaPreviewController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Dev\MediaPreviewController::__invoke
* @see app/Http/Controllers/Dev/MediaPreviewController.php:25
* @route '/dev/media'
*/
MediaPreviewController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: MediaPreviewController.url(options),
    method: 'head',
})

export default MediaPreviewController