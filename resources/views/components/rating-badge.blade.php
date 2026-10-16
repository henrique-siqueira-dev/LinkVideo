@props(['rating'])

<span {{ $attributes->class(['rating-badge', $rating->badgeClasses()]) }} title="{{ $rating->description() }}">{{ $rating->value }}</span>
