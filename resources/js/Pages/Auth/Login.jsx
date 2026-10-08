import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

function FieldIcon({ type }) {
    return (
        <svg className="bp-login-field-icon" viewBox="0 0 24 24" aria-hidden="true">
            {type === 'email' ? <path fill="currentColor" d="M2.25 6.75A2.25 2.25 0 0 1 4.5 4.5h15A2.25 2.25 0 0 1 21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75Zm2.07-.75L12 11.13 19.68 6H4.32ZM20.25 7.82l-7.41 4.93a1.5 1.5 0 0 1-1.68 0L3.75 7.82v9.43c0 .41.34.75.75.75h15a.75.75 0 0 0 .75-.75V7.82Z" /> : <path fill="currentColor" d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25V9h-.75A2.25 2.25 0 0 0 3.75 11.25v9A2.25 2.25 0 0 0 6 22.5h12a2.25 2.25 0 0 0 2.25-2.25v-9A2.25 2.25 0 0 0 18 9h-.75V6.75A5.25 5.25 0 0 0 12 1.5Zm-3 7.5V6.75a3 3 0 0 1 6 0V9H9Zm3 4.5a1.5 1.5 0 0 1 .75 2.8v1.45a.75.75 0 0 1-1.5 0V16.3a1.5 1.5 0 0 1 .75-2.8Z" />}
        </svg>
    );
}

function EyeIcon({ visible }) {
    return (
        <svg className="bp-login-eye-icon" viewBox="0 0 24 24" aria-hidden="true">
            {visible ? <path fill="currentColor" d="M12 5.25c-5.5 0-9.5 6.75-9.5 6.75s4 6.75 9.5 6.75 9.5-6.75 9.5-6.75-4-6.75-9.5-6.75Zm0 11.25a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9Zm0-1.5a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" /> : <path fill="currentColor" d="m3.28 2.22 18.5 18.5-1.06 1.06-3.04-3.04A10.9 10.9 0 0 1 12 18.75c-5.5 0-9.5-6.75-9.5-6.75a20.7 20.7 0 0 1 4.56-4.95L2.22 3.28l1.06-1.06ZM8.2 8.2a4.5 4.5 0 0 0 6.1 6.1L8.2 8.2Zm1.1-1.1 1.24 1.24A3 3 0 0 1 14.66 12l1.24 1.24A4.5 4.5 0 0 0 9.3 7.1ZM12 5.25c5.5 0 9.5 6.75 9.5 6.75a20.7 20.7 0 0 1-3.2 3.78l-1.07-1.07A19.2 19.2 0 0 0 19.94 12 18.7 18.7 0 0 0 12 6.75c-.72 0-1.4.1-2.04.26L8.8 5.84A10.5 10.5 0 0 1 12 5.25Z" />}
        </svg>
    );
}

export default function Login({ status }) {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Masuk" />

            {status && (
                <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                    {status}
                </div>
            )}

            <form onSubmit={submit}>
                <div className="mb-6">
                    <h1 className="bp-login-heading">Selamat Datang</h1>
                    <p className="bp-login-subheading">Masuk untuk mengelola <strong>layanan administrasi karyawan PT Borneo Prima</strong> — cepat, akurat, dan terpantau.</p>
                </div>

                <div>
                    <InputLabel htmlFor="email" value={<><span>Alamat email</span><sup className="bp-login-required">*</sup></>} className="bp-login-label" />

                    <div className="bp-login-input-wrap mt-2">
                        <span className="bp-login-input-prefix"><FieldIcon type="email" /></span>
                        <TextInput
                            id="email"
                            type="email"
                            name="email"
                            value={data.email}
                            placeholder="nama@borneoprima.com"
                            className="bp-login-input"
                            autoComplete="username"
                            onChange={(e) => setData('email', e.target.value)}
                        />
                    </div>

                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="mt-3.5">
                    <InputLabel htmlFor="password" value={<><span>Kata sandi</span><sup className="bp-login-required">*</sup></>} className="bp-login-label" />

                    <div className="bp-login-input-wrap mt-2">
                        <span className="bp-login-input-prefix"><FieldIcon type="password" /></span>
                        <TextInput
                            id="password"
                            type={showPassword ? 'text' : 'password'}
                            name="password"
                            value={data.password}
                            placeholder="••••••••"
                            className="bp-login-input bp-login-password-input"
                            autoComplete="current-password"
                            onChange={(e) => setData('password', e.target.value)}
                        />
                        <button type="button" className="bp-login-eye" onClick={() => setShowPassword((value) => !value)} aria-label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}>
                            <EyeIcon visible={showPassword} />
                        </button>
                    </div>

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="mt-3.5 block">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData('remember', e.target.checked)
                            }
                        />
                        <span className="ms-2 text-sm text-slate-500">
                            Ingat saya di perangkat ini
                        </span>
                    </label>
                </div>

                <div className="mt-7">
                    <button
                        type="submit"
                        className="bp-login-submit disabled:cursor-not-allowed disabled:bg-[#75aafa] disabled:shadow-none disabled:hover:translate-y-0"
                        disabled={processing || !data.email || !data.password}
                    >
                        <svg className="bp-login-submit-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                            <path d="M10 17l5-5-5-5" /><path d="M15 12H3" /><path d="M21 19V5a2 2 0 0 0-2-2h-5" />
                        </svg>
                        {processing ? 'Memproses...' : 'Masuk ke Dashboard'}
                    </button>
                    <p className="mt-4 text-center text-sm text-slate-500">
                        Belum punya akun? <Link href={route('register')} className="font-semibold text-blue-700 hover:underline">Daftar dengan email @borneoprima.com</Link>
                    </p>
                </div>
            </form>
        </GuestLayout>
    );
}
