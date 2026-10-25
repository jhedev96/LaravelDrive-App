/**
 * Formatting helpers shared by the SPA (dashboard widgets, media lists).
 */

export function humanBytes(bytes: number): string {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];

    let value = Number(bytes) || 0;

    let unit = units.shift() as string;

    while (value >= 1024 && units.length) {
        value /= 1024;
        unit = units.shift() as string;
    }

    return `${value >= 10 ? Math.round(value) : value.toFixed(1)} ${unit}`;
}
