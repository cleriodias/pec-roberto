import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status, canResetPassword, units = [] }) {
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        username: '',
        password: '',
        remember: false,
        unit_id: '',
    });

    const preventSubmit = (e) => {
        e.preventDefault();
    };

    const handleUnitLogin = (unitId) => {
        transform((formData) => ({
            ...formData,
            unit_id: unitId,
        }));

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Login" />

            {status && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <form onSubmit={preventSubmit} className="mx-auto w-full max-w-md">
                <div>
                    <InputLabel htmlFor="username" value="Usuário" />

                    <div className="mt-1 flex overflow-hidden rounded-md border border-gray-300 bg-white shadow-sm focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900">
                        <TextInput
                            id="username"
                            type="text"
                            name="username"
                            placeholder="Usuário"
                            value={data.username}
                            className="block w-full border-0 shadow-none focus:border-0 focus:ring-0"
                            autoComplete="username"
                            isFocused={true}
                            onChange={(e) => setData('username', e.target.value)}
                        />
                        <span className="inline-flex items-center border-s border-gray-300 bg-gray-50 px-3 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                            @paoecafepremium.com.br
                        </span>
                    </div>

                    <InputError message={errors.username} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="password" value="Senha" />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        placeholder="Senha"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="mt-4 block">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData('remember', e.target.checked)
                            }
                        />
                        <span className="ms-2 text-sm text-gray-600 dark:text-gray-400">
                            Lembrar
                        </span>
                    </label>
                </div>

                <div className="mt-4 flex items-center justify-end">
                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="no-underline rounded-md text-sm text-gray-600 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100 dark:focus:ring-offset-gray-800"
                        >
                            Esqueceu a senha?
                        </Link>
                    )}

                </div>

                <div className="mt-6">
                    {units.length ? (
                        <div className="mt-3 grid gap-3 sm:grid-cols-3">
                            {units.map((unit) => (
                                <button
                                    type="button"
                                    key={unit.tb2_id}
                                    disabled={processing}
                                    onClick={() => handleUnitLogin(unit.tb2_id)}
                                    className="whitespace-nowrap rounded-md border border-indigo-500 px-4 py-2 text-sm font-semibold text-indigo-600 transition duration-150 ease-in-out hover:bg-indigo-500 hover:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 dark:border-indigo-400 dark:text-indigo-200 dark:hover:bg-indigo-400 dark:hover:text-gray-900"
                                >
                                    {unit.tb2_nome}
                                </button>
                            ))}
                        </div>
                    ) : (
                        <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            Nenhuma unidade cadastrada.
                        </p>
                    )}
                    <InputError message={errors.unit_id} className="mt-2" />
                </div>

        </form>
        </GuestLayout>
    );
}
