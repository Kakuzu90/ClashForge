import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
import unsubscribe from './unsubscribe'
/**
* @see \App\Http\Controllers\Notifications\NotificationController::index
* @see app/Http/Controllers/Notifications/NotificationController.php:24
* @route '/notifications'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/notifications',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Notifications\NotificationController::index
* @see app/Http/Controllers/Notifications/NotificationController.php:24
* @route '/notifications'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Notifications\NotificationController::index
* @see app/Http/Controllers/Notifications/NotificationController.php:24
* @route '/notifications'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Notifications\NotificationController::index
* @see app/Http/Controllers/Notifications/NotificationController.php:24
* @route '/notifications'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Notifications\NotificationController::readAll
* @see app/Http/Controllers/Notifications/NotificationController.php:55
* @route '/notifications/read'
*/
export const readAll = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: readAll.url(options),
    method: 'post',
})

readAll.definition = {
    methods: ["post"],
    url: '/notifications/read',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Notifications\NotificationController::readAll
* @see app/Http/Controllers/Notifications/NotificationController.php:55
* @route '/notifications/read'
*/
readAll.url = (options?: RouteQueryOptions) => {
    return readAll.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Notifications\NotificationController::readAll
* @see app/Http/Controllers/Notifications/NotificationController.php:55
* @route '/notifications/read'
*/
readAll.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: readAll.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Notifications\NotificationController::read
* @see app/Http/Controllers/Notifications/NotificationController.php:45
* @route '/notifications/{id}/read'
*/
export const read = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: read.url(args, options),
    method: 'post',
})

read.definition = {
    methods: ["post"],
    url: '/notifications/{id}/read',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Notifications\NotificationController::read
* @see app/Http/Controllers/Notifications/NotificationController.php:45
* @route '/notifications/{id}/read'
*/
read.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        id: args.id,
    }

    return read.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Notifications\NotificationController::read
* @see app/Http/Controllers/Notifications/NotificationController.php:45
* @route '/notifications/{id}/read'
*/
read.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: read.url(args, options),
    method: 'post',
})

const notifications = {
    unsubscribe: Object.assign(unsubscribe, unsubscribe),
    index: Object.assign(index, index),
    readAll: Object.assign(readAll, readAll),
    read: Object.assign(read, read),
}

export default notifications