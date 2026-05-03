<?php

namespace App\Http\Controllers;

use App\Models\ReferenciaFiscal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FiscalReferenceController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Products/FiscalReferences', [
            'references' => ReferenciaFiscal::query()
                ->orderBy('tb29_descricao')
                ->orderBy('tb29_id')
                ->get([
                    'tb29_id',
                    'tb29_descricao',
                    'tb29_ncm',
                    'tb29_cfop',
                    'tb29_csosn',
                    'tb29_cst',
                    'tb29_cst_ibscbs',
                    'tb29_cclasstrib',
                    'tb29_aliquota_ibs_uf',
                    'tb29_aliquota_ibs_mun',
                    'tb29_aliquota_cbs',
                    'tb29_aliquota_is',
                    'created_at',
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(
            [
                'tb29_descricao' => ['required', 'string', 'max:120'],
                'tb29_ncm' => ['required', 'string', 'size:8'],
                'tb29_cfop' => ['required', 'string', 'size:4'],
                'tb29_csosn' => ['required', 'string', 'max:4'],
                'tb29_cst' => ['required', 'string', 'max:3'],
                'tb29_cst_ibscbs' => ['required', 'string', 'size:3'],
                'tb29_cclasstrib' => ['required', 'string', 'size:6'],
                'tb29_aliquota_ibs_uf' => ['required', 'numeric', 'min:0', 'max:100'],
                'tb29_aliquota_ibs_mun' => ['required', 'numeric', 'min:0', 'max:100'],
                'tb29_aliquota_cbs' => ['required', 'numeric', 'min:0', 'max:100'],
                'tb29_aliquota_is' => ['required', 'numeric', 'min:0', 'max:100'],
            ],
            [
                'tb29_descricao.required' => 'Informe a descricao da referencia fiscal.',
                'tb29_descricao.max' => 'A descricao deve ter no maximo :max caracteres.',
                'tb29_ncm.required' => 'Informe o NCM.',
                'tb29_ncm.size' => 'O NCM deve ter exatamente 8 digitos.',
                'tb29_cfop.required' => 'Informe o CFOP.',
                'tb29_cfop.size' => 'O CFOP deve ter exatamente 4 digitos.',
                'tb29_csosn.required' => 'Informe o CSOSN.',
                'tb29_csosn.max' => 'O CSOSN deve ter no maximo :max caracteres.',
                'tb29_cst.required' => 'Informe o CST.',
                'tb29_cst.max' => 'O CST deve ter no maximo :max caracteres.',
                'tb29_cst_ibscbs.required' => 'Informe o CST IBS/CBS.',
                'tb29_cst_ibscbs.size' => 'O CST IBS/CBS deve ter exatamente 3 digitos.',
                'tb29_cclasstrib.required' => 'Informe o cClassTrib.',
                'tb29_cclasstrib.size' => 'O cClassTrib deve ter exatamente 6 digitos.',
                'tb29_aliquota_ibs_uf.required' => 'Informe a aliquota IBS UF.',
                'tb29_aliquota_ibs_uf.numeric' => 'A aliquota IBS UF deve ser numerica.',
                'tb29_aliquota_ibs_uf.min' => 'A aliquota IBS UF nao pode ser negativa.',
                'tb29_aliquota_ibs_uf.max' => 'A aliquota IBS UF nao pode ultrapassar 100%.',
                'tb29_aliquota_ibs_mun.required' => 'Informe a aliquota IBS Municipio.',
                'tb29_aliquota_ibs_mun.numeric' => 'A aliquota IBS Municipio deve ser numerica.',
                'tb29_aliquota_ibs_mun.min' => 'A aliquota IBS Municipio nao pode ser negativa.',
                'tb29_aliquota_ibs_mun.max' => 'A aliquota IBS Municipio nao pode ultrapassar 100%.',
                'tb29_aliquota_cbs.required' => 'Informe a aliquota CBS.',
                'tb29_aliquota_cbs.numeric' => 'A aliquota CBS deve ser numerica.',
                'tb29_aliquota_cbs.min' => 'A aliquota CBS nao pode ser negativa.',
                'tb29_aliquota_cbs.max' => 'A aliquota CBS nao pode ultrapassar 100%.',
                'tb29_aliquota_is.required' => 'Informe a aliquota IS.',
                'tb29_aliquota_is.numeric' => 'A aliquota IS deve ser numerica.',
                'tb29_aliquota_is.min' => 'A aliquota IS nao pode ser negativa.',
                'tb29_aliquota_is.max' => 'A aliquota IS nao pode ultrapassar 100%.',
            ]
        );

        ReferenciaFiscal::create([
            'tb29_descricao' => $this->normalizeText($data['tb29_descricao'], 120),
            'tb29_ncm' => $this->normalizeDigits($data['tb29_ncm'], 8),
            'tb29_cfop' => $this->normalizeDigits($data['tb29_cfop'], 4),
            'tb29_csosn' => $this->normalizeDigits($data['tb29_csosn'], 4),
            'tb29_cst' => $this->normalizeDigits($data['tb29_cst'], 3),
            'tb29_cst_ibscbs' => $this->normalizeDigits($data['tb29_cst_ibscbs'], 3),
            'tb29_cclasstrib' => $this->normalizeDigits($data['tb29_cclasstrib'], 6),
            'tb29_aliquota_ibs_uf' => round((float) $data['tb29_aliquota_ibs_uf'], 4),
            'tb29_aliquota_ibs_mun' => round((float) $data['tb29_aliquota_ibs_mun'], 4),
            'tb29_aliquota_cbs' => round((float) $data['tb29_aliquota_cbs'], 4),
            'tb29_aliquota_is' => round((float) $data['tb29_aliquota_is'], 4),
        ]);

        return redirect()
            ->route('products.fiscal-references.index')
            ->with('success', 'Referencia fiscal cadastrada com sucesso!');
    }

    private function normalizeDigits(string $value, int $size): string
    {
        return mb_substr(preg_replace('/\D+/', '', $value), 0, $size);
    }

    private function normalizeText(string $value, int $maxLength): string
    {
        return mb_substr(mb_strtoupper(trim($value), 'UTF-8'), 0, $maxLength);
    }
}
