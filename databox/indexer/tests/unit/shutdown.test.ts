import {
    clearShutdownRequest,
    handleShutdownSignal,
    isShutdownRequested,
    onShutdown,
    registerShutdownHandlers,
    shutdownExitCode,
} from '../../src/shutdown';

let stderr: ReturnType<typeof vi.spyOn>;

beforeEach(() => {
    clearShutdownRequest();
    stderr = vi.spyOn(console, 'error').mockImplementation(() => {});
    vi.useFakeTimers();
});

afterEach(() => {
    stderr.mockRestore();
    vi.useRealTimers();
});

const messages = () => stderr.mock.calls.map(c => String(c[0]));

describe('handleShutdownSignal', () => {
    it('does not exit on the first signal', () => {
        expect(isShutdownRequested()).toBe(false);

        handleShutdownSignal('SIGTERM');

        expect(isShutdownRequested()).toBe(true);
        expect(messages().join('\n')).toMatch(
            /Received SIGTERM, finishing what is in flight/
        );
    });

    it('exits on the second one', () => {
        handleShutdownSignal('SIGTERM');

        // vitest intercepts process.exit and turns it into a throw, which is
        // exactly the observable "the process would have stopped here".
        expect(() => handleShutdownSignal('SIGTERM')).toThrow(/process\.exit/);
        expect(messages().join('\n')).toMatch(/again, exiting now/);
    });

    it('exits once the grace period is over', () => {
        handleShutdownSignal('SIGTERM');

        expect(() => vi.advanceTimersByTime(5000)).toThrow(/process\.exit/);
        expect(messages().join('\n')).toMatch(/Still running .* exiting/);
    });

    it('does not keep the process alive with its grace timer', () => {
        // The timer must not be a reason to stay up on its own: a command that
        // finishes inside the grace period has to exit right away.
        const unref = vi.fn();
        const timer = vi
            .spyOn(globalThis, 'setTimeout')
            .mockReturnValue({unref} as unknown as NodeJS.Timeout);

        try {
            handleShutdownSignal('SIGTERM');

            expect(unref).toHaveBeenCalledTimes(1);
        } finally {
            timer.mockRestore();
        }
    });
});

describe('onShutdown', () => {
    it('releases what is registered, on the first signal only', () => {
        const release = vi.fn();
        onShutdown(release);

        handleShutdownSignal('SIGTERM');
        expect(release).toHaveBeenCalledTimes(1);

        expect(() => handleShutdownSignal('SIGTERM')).toThrow(/process\.exit/);
        expect(release).toHaveBeenCalledTimes(1);
    });

    it('reports a listener that throws instead of losing the shutdown', () => {
        onShutdown(() => {
            throw new Error('nope');
        });
        const release = vi.fn();
        onShutdown(release);

        handleShutdownSignal('SIGTERM');

        expect(messages().join('\n')).toMatch(/Shutdown listener failed: nope/);
        expect(release).toHaveBeenCalledTimes(1);
    });
});

describe('shutdownExitCode', () => {
    it('is 130 for SIGINT', () => {
        handleShutdownSignal('SIGINT');

        expect(shutdownExitCode()).toEqual(130);
    });

    it('is 143 for SIGTERM', () => {
        handleShutdownSignal('SIGTERM');

        expect(shutdownExitCode()).toEqual(143);
    });
});

describe('registerShutdownHandlers', () => {
    it('listens to SIGINT and SIGTERM', () => {
        const before = {
            SIGINT: process.listenerCount('SIGINT'),
            SIGTERM: process.listenerCount('SIGTERM'),
        };

        registerShutdownHandlers();

        try {
            expect(process.listenerCount('SIGINT')).toEqual(before.SIGINT + 1);
            expect(process.listenerCount('SIGTERM')).toEqual(
                before.SIGTERM + 1
            );
        } finally {
            process.removeListener('SIGINT', handleShutdownSignal);
            process.removeListener('SIGTERM', handleShutdownSignal);
        }
    });
});
