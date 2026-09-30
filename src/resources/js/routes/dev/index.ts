import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Dev\ComponentGalleryController::__invoke
* @see app/Http/Controllers/Dev/ComponentGalleryController.php:14
* @route '/dev/components'
*/
export const components = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: components.url(options),
    method: 'get',
})

components.definition = {
    methods: ["get","head"],
    url: '/dev/components',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Dev\ComponentGalleryController::__invoke
* @see app/Http/Controllers/Dev/ComponentGalleryController.php:14
* @route '/dev/components'
*/
components.url = (options?: RouteQueryOptions) => {
    return components.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dev\ComponentGalleryController::__invoke
* @see app/Http/Controllers/Dev/ComponentGalleryController.php:14
* @route '/dev/components'
*/
components.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: components.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Dev\ComponentGalleryController::__invoke
* @see app/Http/Controllers/Dev/ComponentGalleryController.php:14
* @route '/dev/components'
*/
components.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: components.url(options),
    method: 'head',
})

const dev = {
    components: Object.assign(components, components),
}

export default dev