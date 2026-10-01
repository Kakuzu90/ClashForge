import RegisterController from './RegisterController'
import ResetLinkRequestController from './ResetLinkRequestController'
import EmailVerificationController from './EmailVerificationController'

const Auth = {
    RegisterController: Object.assign(RegisterController, RegisterController),
    ResetLinkRequestController: Object.assign(ResetLinkRequestController, ResetLinkRequestController),
    EmailVerificationController: Object.assign(EmailVerificationController, EmailVerificationController),
}

export default Auth