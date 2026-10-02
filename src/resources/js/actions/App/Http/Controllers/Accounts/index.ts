import AttachController from './AttachController'
import VerificationController from './VerificationController'
import AccountController from './AccountController'

const Accounts = {
    AttachController: Object.assign(AttachController, AttachController),
    VerificationController: Object.assign(VerificationController, VerificationController),
    AccountController: Object.assign(AccountController, AccountController),
}

export default Accounts