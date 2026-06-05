import { Head, Link, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';

const STEPS = [
    {
        id: 'name',
        title: 'Welcome! What is your name?',
        subtitle: 'Please enter your full name as it is on your ID.',
        type: 'input',
        field: 'name',
        placeholder: 'e.g. Mary Okoro',
    },
    {
        id: 'photo',
        title: 'Upload a recent photo',
        subtitle: 'Please upload a clear picture of your face. This helps employers know who you are.',
        type: 'file',
        field: 'avatar',
    },
    {
        id: 'contact',
        title: 'How can we reach you?',
        subtitle: 'We will use this to send you job updates.',
        type: 'contact',
        fields: ['phone', 'email'],
    },
    {
        id: 'skills',
        title: 'What work can you do?',
        subtitle: 'Select all the work you are good at.',
        type: 'multi-select',
        field: 'skills',
        options: [
            { value: 'cleaning', label: 'Cleaning', icon: '🧹', desc: 'Sweeping, mopping, dusting' },
            { value: 'cooking', label: 'Cooking', icon: '🍳', desc: 'Preparing tasty meals' },
            { value: 'nanny', label: 'Nanny / Childcare', icon: '👶', desc: 'Taking care of children' },
            { value: 'laundry', label: 'Laundry', icon: '🧺', desc: 'Washing and ironing clothes' },
            { value: 'elderly-care', label: 'Elderly Care', icon: '👵', desc: 'Helping aged people' },
            { value: 'live-in', label: 'Live-in Helper', icon: '🏠', desc: 'Staying at the employer house' },
        ]
    },
    {
        id: 'languages',
        title: 'Which languages do you speak?',
        subtitle: 'Tap all the languages you can speak fluently.',
        type: 'multi-select-grid',
        field: 'languages',
        options: [
            { value: 'english', label: 'English', icon: '🇬🇧' },
            { value: 'yoruba', label: 'Yoruba', icon: '🇳🇬' },
            { value: 'igbo', label: 'Igbo', icon: '🇳🇬' },
            { value: 'hausa', label: 'Hausa', icon: '🇳🇬' },
            { value: 'pidgin', label: 'Pidgin', icon: '🗣️' },
            { value: 'french', label: 'French', icon: '🇫🇷' },
        ]
    },
    {
        id: 'experience',
        title: 'How many years have you worked?',
        subtitle: 'This helps employers know your level.',
        type: 'select',
        field: 'experience_years',
        options: [
            { value: '0', label: 'New Helper', icon: '🌱', desc: 'Less than 1 year' },
            { value: '2', label: 'Junior', icon: '🌟', desc: '1 - 3 years' },
            { value: '4', label: 'Senior', icon: '🏆', desc: '3 - 5 years' },
            { value: '7', label: 'Expert', icon: '👑', desc: 'More than 5 years' },
        ]
    },
    {
        id: 'salary',
        title: 'Monthly Salary Expectation',
        subtitle: 'How much do you want to be paid every month (in Naira)?',
        type: 'input',
        field: 'expected_salary',
        placeholder: 'e.g. 50000',
        inputType: 'number'
    },
    {
        id: 'location',
        title: 'Where do you stay?',
        subtitle: 'So we can find jobs near you.',
        type: 'input',
        field: 'location',
        placeholder: 'e.g. Lekki, Lagos',
    },
    {
        id: 'nin',
        title: 'Trust & Safety',
        subtitle: 'We need your NIN to verify your identity. If you are not Nigerian, tick the box below.',
        type: 'nin',
        field: 'nin',
    },
    {
        id: 'password',
        title: 'Create a Secret Code',
        subtitle: 'Choose a password you can remember to log in.',
        type: 'password',
        fields: ['password', 'password_confirmation'],
    }
];

export default function MaidRegister() {
    const [currentStep, setCurrentStep] = useState(0);
    const [preview, setPreview] = useState(null);
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        phone: '',
        skills: [],
        languages: [],
        experience_years: '',
        expected_salary: '',
        location: '',
        nin: '',
        is_foreigner: false,
        password: '',
        password_confirmation: '',
        avatar: null,
        role: 'maid'
    });

    const step = STEPS[currentStep];
    const progress = ((currentStep + 1) / STEPS.length) * 100;

    const isStepValid = () => {
        switch (step.id) {
            case 'name': return data.name.trim().length > 0;
            case 'photo': return data.avatar !== null;
            case 'contact': return data.phone.trim().length > 5;
            case 'skills': return data.skills.length > 0;
            case 'languages': return data.languages.length > 0;
            case 'experience': return data.experience_years !== '';
            case 'salary': return data.expected_salary.toString().trim().length > 0;
            case 'location': return data.location.trim().length > 2;
            case 'nin': return data.is_foreigner || (data.nin.length === 11);
            case 'password': return data.password.length >= 8 && data.password === data.password_confirmation;
            default: return true;
        }
    };

    const nextStep = () => {
        if (!isStepValid()) return;
        
        if (currentStep < STEPS.length - 1) {
            setCurrentStep(currentStep + 1);
        } else {
            submit();
        }
    };

    const prevStep = () => {
        if (currentStep > 0) {
            setCurrentStep(currentStep - 1);
        }
    };

    const handleOptionSelect = (field, value, isMulti = false) => {
        if (isMulti) {
            const currentValues = data[field] || [];
            if (currentValues.includes(value)) {
                setData(field, currentValues.filter(v => v !== value));
            } else {
                setData(field, [...currentValues, value]);
            }
        } else {
            setData(field, value);
            setTimeout(() => nextStep(), 300);
        }
    };

    const submit = () => {
        post('/register/maid');
    };

    const renderStepContent = () => {
        switch (step.type) {
            case 'file':
                return (
                    <div className="space-y-6 animate-fade-in text-center">
                        <div className="relative inline-block group">
                            <div className={`w-48 h-48 rounded-full border-4 ${preview ? 'border-teal' : 'border-gray-200 border-dashed'} overflow-hidden bg-white shadow-inner flex items-center justify-center transition-all`}>
                                {preview ? (
                                    <img src={preview} alt="Preview" className="w-full h-full object-cover" />
                                ) : (
                                    <div className="text-muted flex flex-col items-center">
                                        <span className="text-5xl mb-2">📸</span>
                                        <span className="text-xs font-bold uppercase tracking-widest">Tap to Upload</span>
                                    </div>
                                )}
                            </div>
                            <input
                                type="file"
                                accept="image/*"
                                onChange={e => {
                                    const file = e.target.files[0];
                                    if (file) {
                                        setData('avatar', file);
                                        setPreview(URL.createObjectURL(file));
                                    }
                                }}
                                className="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                            />
                        </div>
                        <div className="bg-amber-50 border border-amber-200 rounded-brand-md p-4 text-left">
                            <p className="text-amber-800 text-xs leading-relaxed">
                                💡 <strong>Tip:</strong> Use a bright, clear photo where your face is visible. This helps employers trust you and hire you faster!
                            </p>
                        </div>
                        {errors.avatar && <p className="text-danger text-sm">{errors.avatar}</p>}
                    </div>
                );
            case 'input':
                return (
                    <div className="space-y-4 animate-fade-in">
                        <input
                            type={step.inputType || 'text'}
                            value={data[step.field]}
                            onChange={e => setData(step.field, e.target.value)}
                            placeholder={step.placeholder}
                            className="w-full h-14 bg-white border-2 border-gray-200 rounded-brand-md px-5 text-lg focus:border-teal outline-none transition-all"
                            autoFocus
                        />
                        {errors[step.field] && <p className="text-danger text-sm">{errors[step.field]}</p>}
                    </div>
                );
            case 'contact':
                return (
                    <div className="space-y-4 animate-fade-in">
                        <div>
                            <label className="block text-sm font-medium text-muted mb-1">Phone Number</label>
                            <input
                                type="tel"
                                value={data.phone}
                                onChange={e => setData('phone', e.target.value)}
                                placeholder="080 1234 5678"
                                className="w-full h-14 bg-white border-2 border-gray-200 rounded-brand-md px-5 text-lg focus:border-teal outline-none transition-all"
                            />
                            {errors.phone && <p className="text-danger text-sm">{errors.phone}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-muted mb-1">Email (Optional)</label>
                            <input
                                type="email"
                                value={data.email}
                                onChange={e => setData('email', e.target.value)}
                                placeholder="you@email.com"
                                className="w-full h-14 bg-white border-2 border-gray-200 rounded-brand-md px-5 text-lg focus:border-teal outline-none transition-all"
                            />
                            {errors.email && <p className="text-danger text-sm">{errors.email}</p>}
                        </div>
                    </div>
                );
            case 'multi-select':
                return (
                    <div className="grid grid-cols-2 gap-3 animate-fade-in">
                        {step.options.map(opt => (
                            <button
                                key={opt.value}
                                onClick={() => handleOptionSelect(step.field, opt.value, true)}
                                className={`p-4 rounded-brand-lg border-2 text-left transition-all ${data[step.field].includes(opt.value) ? 'border-teal bg-teal-ghost shadow-brand-1' : 'border-gray-100 bg-white'}`}
                            >
                                <span className="text-2xl block mb-1">{opt.icon}</span>
                                <span className="font-bold text-sm block">{opt.label}</span>
                                <span className="text-[10px] text-muted leading-tight block">{opt.desc}</span>
                            </button>
                        ))}
                    </div>
                );
            case 'multi-select-grid':
                return (
                    <div className="grid grid-cols-3 gap-2 animate-fade-in">
                        {step.options.map(opt => (
                            <button
                                key={opt.value}
                                onClick={() => handleOptionSelect(step.field, opt.value, true)}
                                className={`p-3 rounded-brand-md border-2 text-center transition-all ${data[step.field].includes(opt.value) ? 'border-teal bg-teal-ghost' : 'border-gray-50 bg-white'}`}
                            >
                                <span className="text-xl block mb-1">{opt.icon}</span>
                                <span className="font-bold text-[10px] block truncate">{opt.label}</span>
                            </button>
                        ))}
                    </div>
                );
            case 'select':
                return (
                    <div className="grid gap-3 animate-fade-in">
                        {step.options.map(opt => (
                            <button
                                key={opt.value}
                                onClick={() => handleOptionSelect(step.field, opt.value)}
                                className={`p-4 rounded-brand-lg border-2 flex items-center gap-4 text-left transition-all ${data[step.field] === opt.value ? 'border-teal bg-teal-ghost shadow-brand-1' : 'border-gray-100 bg-white'}`}
                            >
                                <span className="text-3xl">{opt.icon}</span>
                                <div>
                                    <span className="font-bold text-base block">{opt.label}</span>
                                    <span className="text-xs text-muted block">{opt.desc}</span>
                                </div>
                            </button>
                        ))}
                    </div>
                );
            case 'nin':
                return (
                    <div className="space-y-6 animate-fade-in">
                        <div className="bg-teal-ghost border border-teal/20 rounded-brand-lg p-5">
                            <h4 className="font-bold text-teal flex items-center gap-2 mb-2 text-sm">
                                <span>🛡️</span> Identity Verification
                            </h4>
                            <p className="text-xs text-muted leading-relaxed">
                                Nigerian law requires all domestic workers to be verified. Your data is encrypted and secure.
                            </p>
                        </div>
                        <div className={`${data.is_foreigner ? 'opacity-40 pointer-events-none' : ''}`}>
                            <label className="block text-sm font-medium text-muted mb-1">Enter your 11-digit NIN</label>
                            <input
                                type="text"
                                maxLength="11"
                                value={data.nin}
                                onChange={e => setData('nin', e.target.value.replace(/\D/g, ''))}
                                placeholder="12345678901"
                                className="w-full h-14 bg-white border-2 border-gray-200 rounded-brand-md px-5 text-2xl tracking-[0.2em] font-mono focus:border-teal outline-none transition-all text-center"
                            />
                            {errors.nin && <p className="text-danger text-sm">{errors.nin}</p>}
                        </div>
                        <label className="flex items-center gap-3 p-4 bg-white border border-gray-100 rounded-brand-md cursor-pointer hover:bg-gray-50 transition-all">
                            <input
                                type="checkbox"
                                checked={data.is_foreigner}
                                onChange={e => {
                                    setData('is_foreigner', e.target.checked);
                                    if (e.target.checked) setData('nin', '');
                                }}
                                className="w-5 h-5 accent-teal"
                            />
                            <div className="text-left">
                                <span className="font-bold text-sm block">I am not a Nigerian Citizen</span>
                                <span className="text-[10px] text-muted block">I don't have a NIN because I'm from another country.</span>
                            </div>
                        </label>
                    </div>
                );
            case 'password':
                return (
                    <div className="space-y-4 animate-fade-in">
                        <div>
                            <label className="block text-sm font-medium text-muted mb-1">Secret Code (Password)</label>
                            <input
                                type="password"
                                value={data.password}
                                onChange={e => setData('password', e.target.value)}
                                placeholder="Minimum 8 letters or numbers"
                                className="w-full h-14 bg-white border-2 border-gray-200 rounded-brand-md px-5 text-lg focus:border-teal outline-none transition-all"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-muted mb-1">Type it again</label>
                            <input
                                type="password"
                                value={data.password_confirmation}
                                onChange={e => setData('password_confirmation', e.target.value)}
                                placeholder="Must match secret code above"
                                className="w-full h-14 bg-white border-2 border-gray-200 rounded-brand-md px-5 text-lg focus:border-teal outline-none transition-all"
                            />
                        </div>
                        {errors.password && <p className="text-danger text-sm">{errors.password}</p>}
                    </div>
                );
            default:
                return null;
        }
    };

    return (
        <>
            <Head title="Helper Registration" />
            <div className="min-h-screen bg-ivory flex flex-col">
                {/* Progress Bar */}
                <div className="fixed top-0 left-0 right-0 h-1.5 bg-gray-100 z-50">
                    <div
                        className="h-full bg-teal transition-all duration-500 ease-out"
                        style={{ width: `${progress}%` }}
                    />
                </div>

                {/* Header */}
                <header className="p-6 flex items-center justify-between">
                    <Link href="/">
                        <img src="/maids-logo.png" alt="Maids.ng" className="h-7" />
                    </Link>
                    <div className="text-[10px] font-bold text-muted tracking-widest uppercase bg-white px-3 py-1 rounded-full border border-gray-100">
                        Step {currentStep + 1} / {STEPS.length}
                    </div>
                </header>

                {/* Main Content */}
                <main className="flex-1 flex items-center justify-center p-6 pb-32">
                    <div className="max-w-md w-full">
                        <div className="mb-10 text-center" key={currentStep}>
                            <h1 className="font-display text-3xl font-light text-espresso leading-tight mb-2">
                                {step.title}
                            </h1>
                            <p className="text-muted text-sm leading-relaxed">
                                {step.subtitle}
                            </p>
                        </div>

                        {renderStepContent()}

                        {/* Navigation Footer */}
                        <div className="fixed bottom-0 left-0 right-0 p-6 bg-white/80 backdrop-blur-md border-t border-gray-100 flex items-center gap-4">
                            {currentStep > 0 && (
                                <button
                                    onClick={prevStep}
                                    className="h-14 px-6 rounded-brand-md font-bold text-muted hover:bg-gray-50 transition-all"
                                >
                                    Back
                                </button>
                            )}
                            <button
                                onClick={nextStep}
                                disabled={processing || !isStepValid()}
                                className="flex-1 h-14 bg-teal text-white rounded-brand-md font-bold text-lg shadow-brand-1 hover:bg-teal-dark active:scale-[0.98] transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {processing ? (
                                    <span className="flex items-center justify-center gap-2">
                                        <svg className="animate-spin h-5 w-5" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                                        Saving...
                                    </span>
                                ) : currentStep === STEPS.length - 1 ? 'Finish & Create Account' : 'Continue'}
                            </button>
                        </div>
                    </div>
                </main>

                <style>{`
                    @keyframes fade-in {
                        from { opacity: 0; transform: translateY(10px); }
                        to { opacity: 1; transform: translateY(0); }
                    }
                    .animate-fade-in {
                        animation: fade-in 0.4s ease-out forwards;
                    }
                `}</style>
            </div>
        </>
    );
}
