import { Head, Link } from '@inertiajs/react';

export default function Register() {
    return (
        <>
            <Head title="Join Maids.ng" />
            <div className="min-h-screen bg-ivory flex items-center justify-center px-6 py-12 relative overflow-hidden">
                {/* Decorative background elements */}
                <div className="absolute top-[-200px] left-[-100px] w-[500px] h-[500px] rounded-full opacity-[0.03] bg-teal" />
                <div className="absolute bottom-[-200px] right-[-100px] w-[500px] h-[500px] rounded-full opacity-[0.03] bg-copper" />

                <div className="w-full max-w-2xl relative z-10">
                    <div className="text-center mb-12">
                        <Link href="/">
                            <img src="/maids-logo.png" alt="Maids.ng" className="h-10 mx-auto mb-8" />
                        </Link>
                        <h1 className="font-display text-4xl md:text-5xl font-light text-espresso">
                            How would you like to <em className="italic text-teal">Join Us?</em>
                        </h1>
                        <p className="text-muted text-lg mt-4">Select the option that best describes you</p>
                    </div>

                    <div className="grid md:grid-cols-2 gap-6">
                        {/* Option 1: Employer */}
                        <Link 
                            href="/onboarding"
                            className="group bg-white rounded-brand-2xl p-8 border-2 border-transparent hover:border-teal transition-all duration-500 shadow-brand-1 hover:shadow-brand-3 text-center flex flex-col items-center"
                        >
                            <div className="w-20 h-20 rounded-full bg-teal-ghost flex items-center justify-center text-4xl mb-6 group-hover:scale-110 transition-transform duration-500">
                                🏠
                            </div>
                            <h2 className="font-display text-2xl font-semibold text-espresso mb-3">I need a Helper</h2>
                            <p className="text-muted text-sm leading-relaxed mb-8">
                                I am looking for a professional house help, nanny, cook, or elderly care for my home.
                            </p>
                            <span className="mt-auto inline-flex items-center gap-2 text-teal font-semibold group-hover:gap-3 transition-all">
                                Start Onboarding <span className="text-xl">→</span>
                            </span>
                        </Link>

                        {/* Option 2: Maid/Helper */}
                        <Link 
                            href="/register/maid"
                            className="group bg-white rounded-brand-2xl p-8 border-2 border-transparent hover:border-teal transition-all duration-500 shadow-brand-1 hover:shadow-brand-3 text-center flex flex-col items-center"
                        >
                            <div className="w-20 h-20 rounded-full bg-teal-ghost flex items-center justify-center text-4xl mb-6 group-hover:scale-110 transition-transform duration-500">
                                ✨
                            </div>
                            <h2 className="font-display text-2xl font-semibold text-espresso mb-3">I am a Helper</h2>
                            <p className="text-muted text-sm leading-relaxed mb-8">
                                I am looking for a job and want to register as a verified domestic worker.
                            </p>
                            <span className="mt-auto inline-flex items-center gap-2 text-teal font-semibold group-hover:gap-3 transition-all">
                                Register as Helper <span className="text-xl">→</span>
                            </span>
                        </Link>
                    </div>

                    <p className="text-center text-sm text-muted mt-12">
                        Already have an account? <Link href="/login" className="text-teal font-medium hover:text-teal-dark underline underline-offset-4">Sign In</Link>
                    </p>
                </div>
            </div>
        </>
    );
}
