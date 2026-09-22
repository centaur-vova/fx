export const getDefaults = () => ({
    latency: '--',
    metrics: {
        memory: '--MB',
        freeMem: '--MB',
        connections: '--',
        cpu: '--%',
        taskNum: '--',
        natsStats: {
            messages: 0,
            bytes: 0,
            consumers: 0,
        },
    },
    system: {
        app_version: '...',
        build_date: '...',
        cpu_cores: '--',
        queue_capacity: '--',
        worker_num: 0,
        stream_created_at: '--',
    },
});

export const getFlowDefaults = () => ({
    min: 1,
    max: 100,
    buttons: [
        { label: '1', tasks: 1, class: 'default', stress: false, semaphore_driver: 'shared' },
        { label: '10', tasks: 10, class: 'default', stress: false, semaphore_driver: 'api' },
        { label: '500', tasks: 500, class: 'warning', stress: true, semaphore_driver: 'shared' },
        { label: '1000', tasks: 1000, class: 'accent', stress: true, semaphore_driver: 'api' },
        { label: 'RAND', mode: 'rand', class: 'accent', semaphore_driver: 'api', full_width: true },
    ],
});