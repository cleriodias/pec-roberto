export const BRAZIL_TIME_ZONE = 'America/Sao_Paulo';

const DATE_ONLY_REGEX = /^(\d{4})-(\d{2})-(\d{2})$/;
const DATE_TIME_REGEX = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/;

const BRAZIL_OFFSET_HOURS = 3;

const dateFormatter = new Intl.DateTimeFormat('pt-BR', {
    timeZone: BRAZIL_TIME_ZONE,
});

const dateTimeFormatter = new Intl.DateTimeFormat('pt-BR', {
    timeZone: BRAZIL_TIME_ZONE,
    dateStyle: 'short',
    timeStyle: 'short',
});

const todayInputFormatter = new Intl.DateTimeFormat('en-CA', {
    timeZone: BRAZIL_TIME_ZONE,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
});

const buildBrazilDate = (year, month, day, hour = 0, minute = 0, second = 0) =>
    new Date(Date.UTC(year, month - 1, day, hour + BRAZIL_OFFSET_HOURS, minute, second));

const parseDateValue = (value) => {
    if (!value) {
        return null;
    }

    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }

    if (typeof value !== 'string') {
        const parsed = new Date(value);
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    const normalized = value.trim();

    if (normalized === '') {
        return null;
    }

    const dateOnlyMatch = normalized.match(DATE_ONLY_REGEX);
    if (dateOnlyMatch) {
        return buildBrazilDate(
            Number(dateOnlyMatch[1]),
            Number(dateOnlyMatch[2]),
            Number(dateOnlyMatch[3]),
            12,
        );
    }

    const dateTimeMatch = normalized.match(DATE_TIME_REGEX);
    if (dateTimeMatch) {
        return buildBrazilDate(
            Number(dateTimeMatch[1]),
            Number(dateTimeMatch[2]),
            Number(dateTimeMatch[3]),
            Number(dateTimeMatch[4]),
            Number(dateTimeMatch[5]),
            Number(dateTimeMatch[6] ?? 0),
        );
    }

    const parsed = new Date(normalized);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

export const formatBrazilDate = (value) => {
    const date = parseDateValue(value);

    if (!date) {
        return '--';
    }

    return dateFormatter.format(date);
};

export const formatBrazilDateTime = (value) => {
    const date = parseDateValue(value);

    if (!date) {
        return '--';
    }

    return dateTimeFormatter.format(date);
};

export const getBrazilTodayInputValue = () => todayInputFormatter.format(new Date());
