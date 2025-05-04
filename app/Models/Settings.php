<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Settings extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'column_name',
        'color_type',
        'min_value',
        'max_value',
        'condition_type'
    ];
    
    /**
     * Проверяет, соответствует ли значение условию настройки
     *
     * @param float $value Проверяемое значение
     * @return bool
     */
    public function matchesCondition($value)
    {
        switch ($this->condition_type) {
            case 'range':
                return $value >= $this->min_value && $value <= $this->max_value;
            case 'less_than':
                return $value <= $this->max_value;
            case 'greater_than':
                return $value >= $this->min_value;
            default:
                return false;
        }
    }
    
    /**
     * Получить настройки цвета для определенного столбца и значения
     *
     * @param string $columnName Имя столбца
     * @param float $value Значение для проверки
     * @return string|null Тип цвета или null, если не найдено
     */
    public static function getColorForValue($columnName, $value)
    {
        $settings = self::where('column_name', $columnName)->get();
        
        foreach ($settings as $setting) {
            if ($setting->matchesCondition($value)) {
                return $setting->color_type;
            }
        }
        
        return null;
    }
}
