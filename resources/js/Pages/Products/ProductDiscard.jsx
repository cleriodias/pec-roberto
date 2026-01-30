import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import { Head, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const MIN_CHARACTERS = 3;
const numericRegex = /^\d+$/;

const formatDate = (value) => {
    if (!value) {
        return '--';
    }

    return new Date(value).toLocaleString('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
};

export default function ProductDiscard({ recentDiscards = [] }) {
    const [searchTerm, setSearchTerm] = useState('');
    const [suggestions, setSuggestions] = useState([]);
    const [loading, setLoading] = useState(false);
    const [searchError, setSearchError] = useState('');
    const [selectedProduct, setSelectedProduct] = useState(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        product_id: '',
        quantity: '',
    });

    useEffect(() => {
        const term = searchTerm.trim();
        const isNumeric = numericRegex.test(term);

        if (term.length === 0) {
            setSuggestions([]);
            setSearchError('');
            setLoading(false);
            return;
        }

        if (!isNumeric && term.length < MIN_CHARACTERS) {
            setSuggestions([]);
            setSearchError('');
            setLoading(false);
            return;
        }

        let isCurrent = true;
        setLoading(true);
        setSearchError('');

        fetch(route('products.search', { q: term, type: 1 }), {
            headers: {
                Accept: 'application/json',
            },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Erro ao buscar produtos');
                }

                return response.json();
            })
            .then((productList) => {
                if (!isCurrent) {
                    return;
                }

                setSuggestions(Array.isArray(productList) ? productList : []);
            })
            .catch(() => {
                if (!isCurrent) {
                    return;
                }

                setSuggestions([]);
                setSearchError('Não foi possível buscar produtos.');
            })
            .finally(() => {
                if (!isCurrent) {
                    return;
                }

                setLoading(false);
            });

        return () => {
            isCurrent = false;
        };
    }, [searchTerm]);

    const handleSelectProduct = (product) => {
        setSelectedProduct(product);
        setSearchTerm(product.tb1_nome);
        setSuggestions([]);
        setData('product_id', product.tb1_id);
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        post(route('products.discard.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset('quantity');
                setSelectedProduct(null);
                setSearchTerm('');
                setSuggestions([]);
                setData('product_id', '');
            },
        });
    };

    const suggestionList = useMemo(() => {
        if (suggestions.length === 0) {
            return null;
        }

        return (
            <div className="absolute z-10 mt-1 w-full rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
                <ul className="divide-y divide-gray-100 text-sm dark:divide-gray-700">
                    {suggestions.map((product) => (
                        <li
                            key={product.tb1_id}
                            className="cursor-pointer px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700/50"
                            onClick={() => handleSelectProduct(product)}
                        >
                            <p className="font-semibold text-gray-800 dark:text-gray-100">{product.tb1_nome}</p>
                            <p className="text-xs text-gray-500 dark:text-gray-300">
                                ID #{product.tb1_id} • {product.tb1_codbar || 'Sem código'}
                            </p>
                        </li>
                    ))}
                </ul>
            </div>
        );
    }, [suggestions]);

    const headerContent = (
        <div>
            <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Registro de descarte
            </h2>
            <p className="text-sm text-gray-500 dark:text-gray-300">
                Busque produtos do tipo balança, informe a quantidade descartada e registre para manter o controle.
            </p>
        </div>
    );

    return (
        <AuthenticatedLayout header={headerContent}>
            <Head title="Descarte" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl space-y-8 px-4 sm:px-6 lg:px-8">
                    <div className="rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Buscar produto
                                </label>
                                <div className="relative mt-1">
                                    <input
                                        type="text"
                                        autoComplete="off"
                                        value={searchTerm}
                                        onChange={(event) => setSearchTerm(event.target.value)}
                                        placeholder="Digite nome, código ou ID"
                                        className="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                    />
                                    {loading && (
                                        <span className="absolute inset-y-0 right-3 flex items-center text-gray-400">
                                            <i className="bi bi-arrow-repeat animate-spin" aria-hidden="true"></i>
                                        </span>
                                    )}
                                    {suggestionList}
                                </div>
                                {searchError && (
                                    <p className="mt-2 text-sm text-red-500">{searchError}</p>
                                )}
                                <InputError message={errors.product_id} className="mt-2" />
                            </div>

                            {selectedProduct && (
                                <div className="rounded-xl border border-indigo-100 bg-indigo-50 p-4 text-sm shadow-sm dark:border-indigo-500/40 dark:bg-indigo-900/30">
                                    <p className="font-semibold text-indigo-900 dark:text-white">
                                        {selectedProduct.tb1_nome}
                                    </p>
                                    <p className="text-xs text-indigo-700 dark:text-indigo-200">
                                        ID #{selectedProduct.tb1_id} • {selectedProduct.tb1_codbar || 'Sem código de barras'}
                                    </p>
                                </div>
                            )}

                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Quantidade descartada
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.quantity}
                                    onChange={(event) => setData('quantity', event.target.value)}
                                    className="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                    placeholder="Informe a quantidade"
                                />
                                <InputError message={errors.quantity} className="mt-2" />
                            </div>

                            <div className="flex justify-end">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-xl bg-emerald-600 px-6 py-2 text-sm font-semibold text-white shadow transition hover:bg-emerald-700 disabled:opacity-50"
                                >
                                    Registrar descarte
                                </button>
                            </div>
                        </form>
                    </div>

                    <div className="rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Últimos descartes</h3>
                        {recentDiscards.length === 0 ? (
                            <p className="mt-4 text-sm text-gray-500 dark:text-gray-300">
                                Você ainda não registrou descartes.
                            </p>
                        ) : (
                            <div className="mt-4 overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                                    <thead className="bg-gray-50 dark:bg-gray-900/50">
                                        <tr>
                                            <th className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">
                                                Produto
                                            </th>
                                            <th className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">
                                                Quantidade
                                            </th>
                                            <th className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">
                                                Data/Hora
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                        {recentDiscards.map((discard) => (
                                            <tr key={discard.id}>
                                                <td className="px-3 py-2 text-gray-800 dark:text-gray-100">
                                                    {discard.product?.name ?? 'Produto removido'}
                                                    <span className="block text-xs text-gray-500 dark:text-gray-300">
                                                        ID #{discard.product?.id ?? '-'} •{' '}
                                                        {discard.product?.barcode ?? 'Sem código'}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2 text-gray-800 dark:text-gray-100">
                                                    {Number(discard.quantity).toLocaleString('pt-BR')}
                                                </td>
                                                <td className="px-3 py-2 text-gray-600 dark:text-gray-300">
                                                    {formatDate(discard.created_at)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
