import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Access({
    supplier = null,
    mode = 'supplier',
    authenticationRoute = null,
    backUrl = null,
}) {
    const isAdminAccess = mode === 'admin';
    const { data, setData, post, processing, errors } = useForm({
        access_code: '',
    });

    const handleSubmit = (event) => {
        event.preventDefault();
        post(authenticationRoute ?? route('supplier.authenticate'));
    };

    return (
        <GuestLayout>
            <Head title={isAdminAccess ? 'Senha fornecedor' : 'Fornecedor'} />
            <form onSubmit={handleSubmit}>
                {isAdminAccess && (
                    <div className="mb-6">
                        <h1 className="text-lg font-semibold text-gray-900">
                            Senha do fornecedor
                        </h1>
                        <p className="mt-1 text-sm text-gray-600">
                            Informe a senha de {supplier?.name ?? 'fornecedor'} para visualizar as disputas.
                        </p>
                    </div>
                )}

                <div>
                    <InputLabel
                        htmlFor="access_code"
                        value={isAdminAccess ? 'Senha' : 'Codigo de acesso'}
                    />
                    <TextInput
                        id="access_code"
                        type="text"
                        name="access_code"
                        value={data.access_code}
                        className="mt-1 block w-full"
                        maxLength={4}
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        isFocused={true}
                        onChange={(event) => setData('access_code', event.target.value)}
                        placeholder={isAdminAccess ? 'Digite a senha' : 'Digite o codigo'}
                    />
                    <InputError message={errors.access_code} className="mt-2" />
                </div>

                <div className="mt-6 flex flex-col gap-3">
                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-700 disabled:opacity-50"
                    >
                        Entrar
                    </button>
                    {isAdminAccess && backUrl && (
                        <Link
                            href={backUrl}
                            className="text-center text-sm font-semibold text-gray-600 transition hover:text-gray-900"
                        >
                            Voltar
                        </Link>
                    )}
                </div>
            </form>
        </GuestLayout>
    );
}
