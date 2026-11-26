const priceFormatter = new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: 'GBP',
    maximumFractionDigits: 0,
});

export function formatPrice(price) {
    return priceFormatter.format(price);
}

export function formatDate(iso) {
    if (!iso) {
        return null;
    }

    return new Intl.DateTimeFormat('en-GB', { dateStyle: 'long' }).format(new Date(iso));
}

/**
 * A one-line summary of a saved search, e.g. "Flat · Leeds · £250,000–£300,000 · 2+ beds".
 */
export function describeSearch(search) {
    const { property_type, min_price, max_price, min_bedrooms, region } = search.criteria;

    return [
        property_type && search.property_type_label,
        region,
        describePriceRange(min_price, max_price),
        min_bedrooms !== null && `${min_bedrooms}+ beds`,
    ]
        .filter(Boolean)
        .join(' · ');
}

function describePriceRange(min, max) {
    if (min !== null && max !== null) {
        return `${formatPrice(min)}–${formatPrice(max)}`;
    }

    if (min !== null) {
        return `from ${formatPrice(min)}`;
    }

    return max !== null && `up to ${formatPrice(max)}`;
}

/**
 * The listings URL for a set of criteria, leaving out the "any" ones.
 */
export function listingsUrl(criteria) {
    const query = new URLSearchParams(
        Object.entries(criteria).filter(([, value]) => value !== null && value !== ''),
    );

    return query.size ? `/?${query}` : '/';
}
