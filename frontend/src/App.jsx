import { BrowserRouter } from 'react-router-dom'
import { AuthProvider } from './context/AuthContext.jsx'
import { TenantProvider } from './context/TenantContext.jsx'
import { SubscriptionProvider } from './context/SubscriptionContext.jsx'
import AppRoutes from './routes/AppRoutes.jsx'

function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <TenantProvider>
          <SubscriptionProvider>
            <AppRoutes />
          </SubscriptionProvider>
        </TenantProvider>
      </AuthProvider>
    </BrowserRouter>
  )
}

export default App
