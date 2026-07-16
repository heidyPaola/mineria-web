// assets/js/dashboard.js
// Gráficos específicos del dashboard

document.addEventListener('DOMContentLoaded', function() {
    
    // Gráfico de Viajes por Mes (Línea)
    const viajesPorMesCtx = document.getElementById('viajesPorMesChart');
    if (viajesPorMesCtx && typeof viajesPorMesData !== 'undefined') {
        new Chart(viajesPorMesCtx, {
            type: 'line',
            data: {
                labels: viajesPorMesData.labels,
                datasets: [{
                    label: 'Viajes',
                    data: viajesPorMesData.values,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#f59e0b',
                    pointBorderColor: '#fff',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        labels: {
                            color: '#e5e7eb'
                        }
                    },
                    tooltip: {
                        backgroundColor: '#121623',
                        titleColor: '#f59e0b',
                        bodyColor: '#e5e7eb',
                        borderColor: '#f59e0b',
                        borderWidth: 1
                    }
                },
                scales: {
                    y: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: '#9ca3af'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: '#9ca3af'
                        }
                    }
                }
            }
        });
    }
    
    // Gráfico de Estado de Viajes (Doughnut)
    const estadoViajesCtx = document.getElementById('estadoViajesChart');
    if (estadoViajesCtx && typeof estadoViajesData !== 'undefined') {
        const estados = estadoViajesData.map(item => item.estado);
        const totales = estadoViajesData.map(item => item.total);
        const colores = {
            'pendiente': '#3b82f6',
            'en_progreso': '#f59e0b',
            'completado': '#10b981',
            'cancelado': '#ef4444'
        };
        
        new Chart(estadoViajesCtx, {
            type: 'doughnut',
            data: {
                labels: estados.map(e => e.charAt(0).toUpperCase() + e.slice(1)),
                datasets: [{
                    data: totales,
                    backgroundColor: estados.map(e => colores[e] || '#6b7280'),
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#e5e7eb',
                            font: { size: 12 }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#121623',
                        titleColor: '#f59e0b',
                        bodyColor: '#e5e7eb',
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Gráfico de Top Materiales (Barra)
    const topMaterialesCtx = document.getElementById('topMaterialesChart');
    if (topMaterialesCtx && typeof topMaterialesData !== 'undefined') {
        new Chart(topMaterialesCtx, {
            type: 'bar',
            data: {
                labels: topMaterialesData.labels,
                datasets: [{
                    label: 'Toneladas Transportadas',
                    data: topMaterialesData.values,
                    backgroundColor: 'rgba(245, 158, 11, 0.7)',
                    borderColor: '#f59e0b',
                    borderWidth: 1,
                    borderRadius: 8,
                    barPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        labels: {
                            color: '#e5e7eb'
                        }
                    },
                    tooltip: {
                        backgroundColor: '#121623',
                        titleColor: '#f59e0b',
                        bodyColor: '#e5e7eb',
                        callbacks: {
                            label: function(context) {
                                return `Toneladas: ${context.raw.toFixed(2)} TN`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: '#9ca3af',
                            callback: function(value) {
                                return value.toFixed(0) + ' TN';
                            }
                        },
                        title: {
                            display: true,
                            text: 'Toneladas',
                            color: '#9ca3af'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#9ca3af'
                        }
                    }
                }
            }
        });
    }
});