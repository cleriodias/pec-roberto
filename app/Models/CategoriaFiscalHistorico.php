<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoriaFiscalHistorico extends Model
{
    use HasFactory;

    protected $table = 'tb32_categoria_fiscal_historicos';

    protected $primaryKey = 'tb32_id';

    protected $fillable = [
        'tb30_categoria_fiscal_id',
        'tb1_id',
        'user_id',
        'tb32_acao',
        'tb32_campo',
        'tb32_valor_anterior',
        'tb32_valor_novo',
        'tb32_registros_afetados',
    ];

    public function categoriaFiscal(): BelongsTo
    {
        return $this->belongsTo(CategoriaFiscal::class, 'tb30_categoria_fiscal_id', 'tb30_id');
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'tb1_id', 'tb1_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
