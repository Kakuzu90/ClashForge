import DashboardController from './DashboardController'
import AuditLogController from './AuditLogController'
import UserController from './UserController'
import SanctionController from './SanctionController'

const Admin = {
    DashboardController: Object.assign(DashboardController, DashboardController),
    AuditLogController: Object.assign(AuditLogController, AuditLogController),
    UserController: Object.assign(UserController, UserController),
    SanctionController: Object.assign(SanctionController, SanctionController),
}

export default Admin