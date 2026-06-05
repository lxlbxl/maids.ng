import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

/**
 * BottomNavBar - Mobile responsive bottom navigation bar
 * 
 * @param {Array} items - Navigation items [{name, href, icon}]
 * @param {Array} moreItems - Additional items shown in "More" menu [{name, href, icon}]
 * @param {string} activeColor - Active item color class (e.g., 'text-teal', 'text-teal')
 * @param {string} activeBg - Active item background class (e.g., 'bg-teal/10', 'bg-white/5')
 * @param {string} bgColor - Bottom bar background class
 * @param {string} borderColor - Border class
 * @param {Function} onLogout - Logout handler
 */
export default function BottomNavBar({
    items = [],
    moreItems = [],
    activeColor = 'text-teal',
    activeBg = 'bg-teal/10',
    bgColor = 'bg-white',
    borderColor = 'border-gray-200',
    onLogout
}) {
    const [showMore, setShowMore] = useState(false);
    const { url } = usePage();

    const isActive = (href) => {
        if (typeof window === 'undefined') return false;
        return window.location.pathname === href;
    };

    const allItems = [...items, ...moreItems];

    return (
        <>
            {/* Bottom Navigation Bar - Mobile Only */}
            <nav className={`md:hidden fixed bottom-0 left-0 right-0 ${bgColor} ${borderColor} border-t z-50 safe-area-bottom`}>
                <div className="flex items-center justify-around h-16 px-2">
                    {items.map((item) => (
                        <Link
                            key={item.name}
                            href={item.href}
                            className={`flex flex-col items-center justify-center gap-0.5 px-3 py-2 rounded-lg transition-all min-w-[60px] ${isActive(item.href)
                                    ? `${activeColor} ${activeBg}`
                                    : 'text-gray-500 hover:text-gray-700'
                                }`}
                        >
                            <span className="text-xl">{item.icon}</span>
                            <span className="text-[10px] font-medium truncate max-w-[60px]">{item.name}</span>
                        </Link>
                    ))}

                    {/* More Menu Button */}
                    {moreItems.length > 0 && (
                        <button
                            onClick={() => setShowMore(!showMore)}
                            className={`flex flex-col items-center justify-center gap-0.5 px-3 py-2 rounded-lg transition-all min-w-[60px] ${showMore
                                    ? `${activeColor} ${activeBg}`
                                    : 'text-gray-500 hover:text-gray-700'
                                }`}
                        >
                            <span className="text-xl">{showMore ? '✕' : '⋯'}</span>
                            <span className="text-[10px] font-medium">More</span>
                        </button>
                    )}
                </div>
            </nav>

            {/* More Menu Dropdown */}
            {showMore && moreItems.length > 0 && (
                <>
                    {/* Backdrop */}
                    <div
                        className="md:hidden fixed inset-0 bg-black/50 z-50"
                        onClick={() => setShowMore(false)}
                    />

                    {/* Menu Panel */}
                    <div className={`md:hidden fixed bottom-16 left-0 right-0 ${bgColor} ${borderColor} border-t z-50 shadow-2xl`}>
                        <div className="p-4 space-y-1 max-h-[60vh] overflow-y-auto">
                            <p className="font-mono text-[10px] tracking-[0.16em] uppercase text-gray-400 mb-3 px-3">More Options</p>
                            {moreItems.map((item) => (
                                <Link
                                    key={item.name}
                                    href={item.href}
                                    onClick={() => setShowMore(false)}
                                    className={`flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium transition-all ${isActive(item.href)
                                            ? `${activeColor} ${activeBg}`
                                            : 'text-gray-600 hover:bg-gray-50'
                                        }`}
                                >
                                    <span className="text-lg">{item.icon}</span>
                                    <span>{item.name}</span>
                                </Link>
                            ))}
                            {onLogout && (
                                <div className="pt-3 mt-3 border-t border-gray-100">
                                    <button
                                        onClick={onLogout}
                                        className="w-full flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-red-500 hover:bg-red-50 transition-colors"
                                    >
                                        <span className="text-lg">🚪</span>
                                        <span>Logout</span>
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </>
            )}

            {/* Spacer to prevent content being hidden behind bottom nav on mobile */}
            <div className="md:hidden h-16" />
        </>
    );
}