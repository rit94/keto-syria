<?php

namespace App\Enums;

enum FoodCategory: string
{
    case Vegetables = 'vegetables';
    case Fruits = 'fruits';
    case Meat = 'meat';
    case Poultry = 'poultry';
    case Fish = 'fish';
    case Eggs = 'eggs';
    case Dairy = 'dairy';
    case Cheese = 'cheese';
    case Fats = 'fats';
    case Nuts = 'nuts';
    case Herbs = 'herbs';
    case Condiments = 'condiments';
    case Beverages = 'beverages';
    case Other = 'other';

    public function label(): string
    {
        return __('enums.food_category.'.$this->value);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
