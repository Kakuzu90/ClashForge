import AuthenticatedSessionController from './AuthenticatedSessionController'
import PasswordResetLinkController from './PasswordResetLinkController'
import NewPasswordController from './NewPasswordController'
import ConfirmablePasswordController from './ConfirmablePasswordController'

const Controllers = {
    AuthenticatedSessionController: Object.assign(AuthenticatedSessionController, AuthenticatedSessionController),
    PasswordResetLinkController: Object.assign(PasswordResetLinkController, PasswordResetLinkController),
    NewPasswordController: Object.assign(NewPasswordController, NewPasswordController),
    ConfirmablePasswordController: Object.assign(ConfirmablePasswordController, ConfirmablePasswordController),
}

export default Controllers