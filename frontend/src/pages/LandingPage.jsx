import { Navbar } from '../components/landing/Navbar.jsx'
import { Hero } from '../components/landing/Hero.jsx'
import { Features } from '../components/landing/Features.jsx'
import { MultiTenant } from '../components/landing/MultiTenant.jsx'
import { HowItWorks } from '../components/landing/HowItWorks.jsx'
import { Pricing } from '../components/landing/Pricing.jsx'
import { Roles } from '../components/landing/Roles.jsx'
import { Cta } from '../components/landing/Cta.jsx'
import { Footer } from '../components/landing/Footer.jsx'

export default function LandingPage() {
  return (
    <div className="min-h-screen bg-white">
      <Navbar />
      <main>
        <Hero />
        <Features />
        <MultiTenant />
        <HowItWorks />
        <Pricing />
        <Roles />
        <Cta />
      </main>
      <Footer />
    </div>
  )
}
