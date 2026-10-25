/**
 * Pan/zoom utility — TS port of the legacy `mixin/library/panzoom.js`.
 * Attaches wheel zoom (around the cursor) + drag panning to elements
 * matching a selector, keeping their `matrix(...)` transform.
 */
interface TransformMatrix {
    scale: number;
    transX: number;
    transY: number;
}

interface PanZoomOptions {
    minScale?: number;
    maxScale?: number;
    increment?: number;
    liner?: boolean;
}

export function getTransformMatrix(ele: HTMLElement): TransformMatrix {
    const trans = ele.style.transform;
    const start = trans.indexOf('(') + 1;
    const end = trans.indexOf(')');
    const matrix = (end > start ? trans.slice(start, end) : '1,0,0,1,0,0').split(',');

    return {
        scale: Number(matrix[0]),
        transX: Number(matrix[4]),
        transY: Number(matrix[5]),
    };
}

export function setTransformMatrix(ele: HTMLElement, o: TransformMatrix): void {
    ele.style.transform = `matrix(${o.scale}, 0, 0, ${o.scale}, ${o.transX}, ${o.transY})`;
}

function attachPanZoom(
    ele: HTMLElement,
    minScale: number,
    maxScale: number,
    increment: number,
    liner: boolean,
): void {
    let panning: boolean | null = null;
    let oldX = 0;
    let oldY = 0;

    ele.style.transform = 'matrix(1, 0, 0, 1, 0, 0)';

    const applyTranslate = (dx: number, dy: number): void => {
        const next = getTransformMatrix(ele);

        next.transX += dx;
        next.transY += dy;

        setTransformMatrix(ele, next);
    };

    const applyScale = (dscale: number, x: number, y: number): void => {
        const next = getTransformMatrix(ele);
        const tranX = x - (ele.clientWidth / 2);
        const tranY = y - (ele.clientHeight / 2);

        dscale = liner ? dscale : dscale * next.scale;

        next.scale += dscale;

        if (next.scale < minScale) {
            next.scale = minScale;
        }

        if (next.scale > maxScale) {
            next.scale = maxScale;
        }

        next.transX += (1 - next.scale) * tranX - next.transX;
        next.transY += (1 - next.scale) * tranY - next.transY;

        setTransformMatrix(ele, next);
    };

    ele.addEventListener('mousedown', (e: MouseEvent) => {
        panning = true;

        oldX = e.clientX;
        oldY = e.clientY;

        e.preventDefault();
    });

    ele.addEventListener('mousemove', (e: MouseEvent) => {
        if (panning) {
            applyTranslate(e.clientX - oldX, e.clientY - oldY);

            oldX = e.clientX;
            oldY = e.clientY;
        }
    });

    const stopPanning = () => {
        panning = null;
    };

    ele.addEventListener('mouseup', stopPanning);
    ele.addEventListener('mouseleave', stopPanning);

    ele.addEventListener('wheel', (e: WheelEvent) => {
        e.preventDefault();

        const delta = Math.max(-1, Math.min(1, - e.deltaY));

        applyScale(delta * (e.shiftKey ? increment * 2 : increment), e.clientX, e.clientY);
    });
}

export function initPanZoom(selector: string, opts: PanZoomOptions = {}): unknown[] {
    const minScale = opts.minScale ?? 0.1;
    const maxScale = opts.maxScale ?? 5;
    const increment = opts.increment ?? 0.05;
    const liner = opts.liner ?? false;

    const attached: unknown[] = [];

    document.querySelectorAll<HTMLElement>(selector).forEach((ele) => {
        attachPanZoom(ele, minScale, maxScale, increment, liner);
    });

    return attached;
}
