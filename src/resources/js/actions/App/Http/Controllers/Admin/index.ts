import DashboardController from './DashboardController'
import AuditLogController from './AuditLogController'
import UserController from './UserController'

const Admin = {
    DashboardController: Object.assign(DashboardController, DashboardController),
    AuditLogController: Object.assign(AuditLogController, AuditLogController),
    UserController: Object.assign(UserController, UserController),
}

export default Admin