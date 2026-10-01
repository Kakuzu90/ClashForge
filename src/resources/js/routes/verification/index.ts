import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\EmailVerificationController::verify
* @see app/Http/Controllers/Auth/EmailVerificationController.php:45
* @route '/email/verify/{ulid}/{hash}'
*/
export const verify = (args: { ulid: string | number, hash: string | number } | [ulid: string | number, hash: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: verify.url(args, options),
    method: 'get',
})

verify.definition = {
    methods: ["get","head"],
    url: '/email/verify/{ulid}/{hash}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\EmailVerificationController::verify
* @see app/Http/Controllers/Auth/EmailVerificationController.php:45
* @route '/email/verify/{ulid}/{hash}'
*/
verify.url = (args: { ulid: string | number, hash: string | number } | [ulid: string | number, hash: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            ulid: args[0],
            hash: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        ulid: args.ulid,
        hash: args.hash,
    }

    return verify.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace('{hash}', parsedArgs.hash.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\EmailVerificationController::verify
* @see app/Http/Controllers/Auth/EmailVerificationController.php:45
* @route '/email/verify/{ulid}/{hash}'
*/
verify.get = (args: { ulid: string | number, hash: string | number } | [ulid: string | number, hash: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: verify.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\EmailVerificationController::verify
* @see app/Http/Controllers/Auth/EmailVerificationController.php:45
* @route '/email/verify/{ulid}/{hash}'
*/
verify.head = (args: { ulid: string | number, hash: string | number } | [ulid: string | number, hash: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: verify.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Auth\EmailVerificationController::confirm
* @see app/Http/Controllers/Auth/EmailVerificationController.php:57
* @route '/email/verify/{ulid}/{hash}'
*/
export const confirm = (args: { ulid: string | number, hash: string | number } | [ulid: string | number, hash: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: confirm.url(args, options),
    method: 'post',
})

confirm.definition = {
    methods: ["post"],
    url: '/email/verify/{ulid}/{hash}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\EmailVerificationController::confirm
* @see app/Http/Controllers/Auth/EmailVerificationController.php:57
* @route '/email/verify/{ulid}/{hash}'
*/
confirm.url = (args: { ulid: string | number, hash: string | number } | [ulid: string | number, hash: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            ulid: args[0],
            hash: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        ulid: args.ulid,
        hash: args.hash,
    }

    return confirm.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace('{hash}', parsedArgs.hash.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\EmailVerificationController::confirm
* @see app/Http/Controllers/Auth/EmailVerificationController.php:57
* @route '/email/verify/{ulid}/{hash}'
*/
confirm.post = (args: { ulid: string | number, hash: string | number } | [ulid: string | number, hash: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: confirm.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Auth\EmailVerificationController::result
* @see app/Http/Controllers/Auth/EmailVerificationController.php:68
* @route '/email/verified'
*/
export const result = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: result.url(options),
    method: 'get',
})

result.definition = {
    methods: ["get","head"],
    url: '/email/verified',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\EmailVerificationController::result
* @see app/Http/Controllers/Auth/EmailVerificationController.php:68
* @route '/email/verified'
*/
result.url = (options?: RouteQueryOptions) => {
    return result.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\EmailVerificationController::result
* @see app/Http/Controllers/Auth/EmailVerificationController.php:68
* @route '/email/verified'
*/
result.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: result.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\EmailVerificationController::result
* @see app/Http/Controllers/Auth/EmailVerificationController.php:68
* @route '/email/verified'
*/
result.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: result.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Auth\EmailVerificationController::notice
* @see app/Http/Controllers/Auth/EmailVerificationController.php:27
* @route '/email/verify'
*/
export const notice = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: notice.url(options),
    method: 'get',
})

notice.definition = {
    methods: ["get","head"],
    url: '/email/verify',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\EmailVerificationController::notice
* @see app/Http/Controllers/Auth/EmailVerificationController.php:27
* @route '/email/verify'
*/
notice.url = (options?: RouteQueryOptions) => {
    return notice.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\EmailVerificationController::notice
* @see app/Http/Controllers/Auth/EmailVerificationController.php:27
* @route '/email/verify'
*/
notice.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: notice.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\EmailVerificationController::notice
* @see app/Http/Controllers/Auth/EmailVerificationController.php:27
* @route '/email/verify'
*/
notice.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: notice.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Auth\EmailVerificationController::send
* @see app/Http/Controllers/Auth/EmailVerificationController.php:80
* @route '/email/verification-notification'
*/
export const send = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: send.url(options),
    method: 'post',
})

send.definition = {
    methods: ["post"],
    url: '/email/verification-notification',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\EmailVerificationController::send
* @see app/Http/Controllers/Auth/EmailVerificationController.php:80
* @route '/email/verification-notification'
*/
send.url = (options?: RouteQueryOptions) => {
    return send.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\EmailVerificationController::send
* @see app/Http/Controllers/Auth/EmailVerificationController.php:80
* @route '/email/verification-notification'
*/
send.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: send.url(options),
    method: 'post',
})

const verification = {
    verify: Object.assign(verify, verify),
    confirm: Object.assign(confirm, confirm),
    result: Object.assign(result, result),
    notice: Object.assign(notice, notice),
    send: Object.assign(send, send),
}

export default verification