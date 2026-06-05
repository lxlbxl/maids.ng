import React, { useState, useRef, useEffect } from 'react';

/**
 * Ambassador Chat Widget — embedded AI assistant for Maids.ng
 *
 * Usage:
 *   <AmbassadorChatWidget />
 *
 * Props:
 *   phone?: string        — Pre-filled phone number (for authenticated users)
 *   email?: string        — Pre-filled email (for authenticated users)
 *   sessionId?: string    — Custom session ID
 *   onConversationCreated?: (id: number) => void
 */
export default function AmbassadorChatWidget({
    phone = '',
    email = '',
    sessionId = '',
    onConversationCreated,
}) {
    const [isOpen, setIsOpen] = useState(false);
    const [messages, setMessages] = useState([]);
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(false);
    const [conversationId, setConversationId] = useState(null);
    const messagesEndRef = useRef(null);

    const apiUrl = '/ambassador/chat';

    useEffect(() => {
        scrollToBottom();
    }, [messages]);

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    const sendMessage = async () => {
        if (!input.trim() || loading) return;

        const userMessage = { role: 'user', content: input.trim() };
        setMessages(prev => [...prev, userMessage]);
        setInput('');
        setLoading(true);

        try {
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    message: input.trim(),
                    session_id: sessionId || '',
                    phone: phone || '',
                    email: email || '',
                }),
            });

            const data = await response.json();

            if (data.success) {
                setMessages(prev => [
                    ...prev,
                    { role: 'assistant', content: data.content },
                ]);
                setConversationId(data.conversation_id);
                onConversationCreated?.(data.conversation_id);
            } else {
                setMessages(prev => [
                    ...prev,
                    { role: 'assistant', content: "I'm sorry, something went wrong. Please try again." },
                ]);
            }
        } catch (error) {
            console.error('Chat error:', error);
            setMessages(prev => [
                ...prev,
                { role: 'assistant', content: "I'm having trouble connecting. Please try again later." },
            ]);
        } finally {
            setLoading(false);
        }
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    };

    return (
        <>
            {/* Chat Button */}
            {!isOpen && (
                <button
                    onClick={() => setIsOpen(true)}
                    className="fixed bottom-6 right-6 w-16 h-16 bg-teal text-white rounded-full shadow-brand-3 flex items-center justify-center transition-all z-50 hover:scale-110 active:scale-95 group overflow-hidden"
                    aria-label="Open chat"
                >
                    <div className="absolute inset-0 bg-white/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <svg className="w-7 h-7 relative z-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    <span className="absolute -top-1 -right-1 w-4 h-4 bg-copper rounded-full border-2 border-white animate-pulse"></span>
                </button>
            )}

            {/* Chat Window */}
            {isOpen && (
                <div className="fixed inset-0 sm:inset-auto sm:bottom-6 sm:right-6 w-full sm:w-[420px] h-[100dvh] sm:h-[600px] bg-white sm:rounded-brand-lg shadow-brand-3 flex flex-col z-[100] overflow-hidden animate-spring">
                    {/* Header */}
                    <div className="bg-teal text-white px-6 py-5 flex items-center justify-between border-b border-teal-dark shadow-sm relative overflow-hidden">
                        <div className="absolute top-0 right-0 p-8 opacity-10 pointer-events-none">
                            <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="0.5">
                                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
                            </svg>
                        </div>
                        
                        <div className="relative z-10">
                            <div className="flex items-center gap-3">
                                <div className="w-10 h-10 bg-white/10 rounded-brand-sm flex items-center justify-center border border-white/20">
                                    <span className="text-xl">🤵</span>
                                </div>
                                <div>
                                    <h3 className="font-display font-medium text-xl leading-tight">Concierge AI</h3>
                                    <div className="flex items-center gap-2 mt-0.5">
                                        <span className="w-2 h-2 rounded-full bg-success animate-pulse"></span>
                                        <span className="font-mono text-[9px] uppercase tracking-widest text-teal-pale opacity-80">Ambassador Node Active</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <button
                            onClick={() => setIsOpen(false)}
                            className="bg-white/10 hover:bg-white/20 text-white p-2 rounded-brand-sm transition-all relative z-10"
                            aria-label="Close chat"
                        >
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {/* Messages */}
                    <div className="flex-1 overflow-y-auto px-6 py-6 space-y-6 bg-ivory scroll-smooth">
                        {messages.length === 0 && (
                            <div className="text-center py-10 px-4">
                                <div className="w-16 h-16 bg-teal-ghost rounded-full flex items-center justify-center mx-auto mb-6">
                                    <span className="text-2xl">✨</span>
                                </div>
                                <h4 className="font-display text-2xl text-teal mb-3">How can I assist you today?</h4>
                                <p className="text-sm text-muted max-w-[240px] mx-auto leading-relaxed">
                                    I am your personal Maids.ng ambassador, here to help you find and secure verified domestic help.
                                </p>
                            </div>
                        )}
                        
                        {messages.map((msg, i) => (
                            <div
                                key={i}
                                className={`flex flex-col ${msg.role === 'user' ? 'items-end' : 'items-start'}`}
                            >
                                <div
                                    className={`max-w-[88%] px-5 py-4 text-[15px] leading-relaxed shadow-brand-1 transition-all ${msg.role === 'user'
                                            ? 'bg-teal text-white rounded-brand-md rounded-tr-none'
                                            : 'bg-white text-espresso rounded-brand-md rounded-tl-none border border-linen'
                                        }`}
                                >
                                    {msg.content}
                                </div>
                                <span className="font-mono text-[8px] uppercase tracking-tighter mt-2 text-muted opacity-50">
                                    {msg.role === 'user' ? 'Sent by You' : 'Agent Response'}
                                </span>
                            </div>
                        ))}
                        
                        {loading && (
                            <div className="flex flex-col items-start">
                                <div className="bg-white px-5 py-4 rounded-brand-md rounded-tl-none border border-linen shadow-brand-1">
                                    <div className="flex items-center gap-2">
                                        <div className="w-1.5 h-1.5 bg-teal/40 rounded-full animate-bounce" style={{ animationDelay: '0ms' }}></div>
                                        <div className="w-1.5 h-1.5 bg-teal/60 rounded-full animate-bounce" style={{ animationDelay: '200ms' }}></div>
                                        <div className="w-1.5 h-1.5 bg-teal/80 rounded-full animate-bounce" style={{ animationDelay: '400ms' }}></div>
                                        <span className="font-mono text-[9px] uppercase tracking-widest text-muted ml-2">Consulting Data</span>
                                    </div>
                                </div>
                            </div>
                        )}
                        <div ref={messagesEndRef} />
                    </div>

                    {/* Input */}
                    <div className="p-5 bg-white border-t border-linen">
                        <div className="relative group">
                            <textarea
                                value={input}
                                onChange={(e) => setInput(e.target.value)}
                                onKeyDown={handleKeyDown}
                                placeholder="Describe what you're looking for..."
                                rows={1}
                                className="w-full resize-none border-linen bg-ivory rounded-brand-md pl-5 pr-14 py-4 text-sm focus:outline-none focus:ring-2 focus:ring-teal/20 focus:border-teal transition-all placeholder:text-muted/50"
                                disabled={loading}
                            />
                            <button
                                onClick={sendMessage}
                                disabled={loading || !input.trim()}
                                className="absolute right-2 top-2 w-10 h-10 bg-teal text-white rounded-brand-sm flex items-center justify-center transition-all hover:bg-teal-dark disabled:bg-linen disabled:text-muted/30 shadow-sm"
                                aria-label="Send message"
                            >
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                </svg>
                            </button>
                        </div>
                        <div className="mt-3 flex items-center justify-between">
                            <p className="font-mono text-[8px] uppercase tracking-[0.2em] text-muted opacity-40">Secure Neural Channel</p>
                            <p className="font-mono text-[8px] uppercase tracking-[0.2em] text-muted opacity-40">Maids.ng v3.0</p>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}