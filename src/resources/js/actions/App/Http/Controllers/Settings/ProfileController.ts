import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\ProfileController::edit
* @see app/Http/Controllers/Settings/ProfileController.php:28
* @route '/settings/profile'
*/
export const edit = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/settings/profile',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\ProfileController::edit
* @see app/Http/Controllers/Settings/ProfileController.php:28
* @route '/settings/profile'
*/
edit.url = (options?: RouteQueryOptions) => {
    return edit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\ProfileController::edit
* @see app/Http/Controllers/Settings/ProfileController.php:28
* @route '/settings/profile'
*/
edit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\ProfileController::edit
* @see app/Http/Controllers/Settings/ProfileController.php:28
* @route '/settings/profile'
*/
edit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Settings\ProfileController::update
* @see app/Http/Controllers/Settings/ProfileController.php:48
* @route '/settings/profile'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/settings/profile',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Settings\ProfileController::update
* @see app/Http/Controllers/Settings/ProfileController.php:48
* @route '/settings/profile'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\ProfileController::update
* @see app/Http/Controllers/Settings/ProfileController.php:48
* @route '/settings/profile'
*/
update.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Settings\ProfileController::updateUsername
* @see app/Http/Controllers/Settings/ProfileController.php:55
* @route '/settings/profile/username'
*/
export const updateUsername = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateUsername.url(options),
    method: 'put',
})

updateUsername.definition = {
    methods: ["put"],
    url: '/settings/profile/username',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Settings\ProfileController::updateUsername
* @see app/Http/Controllers/Settings/ProfileController.php:55
* @route '/settings/profile/username'
*/
updateUsername.url = (options?: RouteQueryOptions) => {
    return updateUsername.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\ProfileController::updateUsername
* @see app/Http/Controllers/Settings/ProfileController.php:55
* @route '/settings/profile/username'
*/
updateUsername.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateUsername.url(options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Settings\ProfileController::setAvatar
* @see app/Http/Controllers/Settings/ProfileController.php:67
* @route '/settings/profile/avatar'
*/
export const setAvatar = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: setAvatar.url(options),
    method: 'put',
})

setAvatar.definition = {
    methods: ["put"],
    url: '/settings/profile/avatar',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Settings\ProfileController::setAvatar
* @see app/Http/Controllers/Settings/ProfileController.php:67
* @route '/settings/profile/avatar'
*/
setAvatar.url = (options?: RouteQueryOptions) => {
    return setAvatar.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\ProfileController::setAvatar
* @see app/Http/Controllers/Settings/ProfileController.php:67
* @route '/settings/profile/avatar'
*/
setAvatar.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: setAvatar.url(options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Settings\ProfileController::removeAvatar
* @see app/Http/Controllers/Settings/ProfileController.php:74
* @route '/settings/profile/avatar'
*/
export const removeAvatar = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: removeAvatar.url(options),
    method: 'delete',
})

removeAvatar.definition = {
    methods: ["delete"],
    url: '/settings/profile/avatar',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Settings\ProfileController::removeAvatar
* @see app/Http/Controllers/Settings/ProfileController.php:74
* @route '/settings/profile/avatar'
*/
removeAvatar.url = (options?: RouteQueryOptions) => {
    return removeAvatar.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\ProfileController::removeAvatar
* @see app/Http/Controllers/Settings/ProfileController.php:74
* @route '/settings/profile/avatar'
*/
removeAvatar.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: removeAvatar.url(options),
    method: 'delete',
})

const ProfileController = { edit, update, updateUsername, setAvatar, removeAvatar }

export default ProfileController